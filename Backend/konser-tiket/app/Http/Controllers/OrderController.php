<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\Seat;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class OrderController extends Controller
{
    /**
     * Display a list of the customer's orders.
     */
    public function index(Request $request)
    {
        $orders = Order::with(['event', 'orderDetails.ticketType', 'orderDetails.seat', 'payment'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'orders' => $orders
        ]);
    }

    /**
     * Store a new ticket order (Checkout).
     */
    public function store(Request $request)
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
            'tickets' => 'nullable|array',
            'tickets.*.ticket_type_id' => 'required_with:tickets|exists:ticket_types,id',
            'tickets.*.quantity' => 'required_with:tickets|integer|min:1',
            'tickets.*.seat_ids' => 'nullable|array',
            'tickets.*.seat_ids.*' => 'exists:seats,id',
            'items' => 'nullable|array',
            'items.*.ticket_type_id' => 'required_with:items|exists:ticket_types,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.seat_ids' => 'nullable|array',
            'items.*.seat_ids.*' => 'exists:seats,id',
        ]);

        $orderItems = $request->input('tickets') ?: $request->input('items');
        if (empty($orderItems)) {
            return response()->json(['message' => 'Silakan pilih tiket terlebih dahulu.'], 422);
        }

        $event = Event::find($request->event_id);
        if ($event->status !== 'published') {
            return response()->json(['message' => 'Cannot order tickets for this event as it is not published.'], 400);
        }

        $userId = $request->user()->id;
        $totalPrice = 0;
        $orderDetailsToCreate = [];
        $acquiredLocks = [];
        $checkoutLock = Cache::lock("event_checkout_{$event->id}", 20);
        $queuePositionKey = "event_queue_count_{$event->id}";
        $queuePosition = Cache::increment($queuePositionKey);

        try {
            // Queue concurrent buyers for the same event before changing quotas or seats.
            if (!$checkoutLock->block(10)) {
                Cache::decrement($queuePositionKey);
                return response()->json([
                    'message' => "Mohon tunggu sebentar, Anda sedang di antrean (No. Urut Antrean: #{$queuePosition}). Silakan coba beberapa saat lagi."
                ], 429);
            }
            Cache::decrement($queuePositionKey);
            DB::beginTransaction();

            foreach ($orderItems as $item) {
                $ticketType = TicketType::lockForUpdate()->find($item['ticket_type_id']);
                
                // 1. Check ticket type belongs to this event
                if ($ticketType->event_id !== $event->id) {
                    DB::rollBack();
                    return response()->json(['message' => 'Ticket type does not belong to this event.'], 400);
                }

                // 2. Check quota
                if ($ticketType->quota < $item['quantity']) {
                    DB::rollBack();
                    return response()->json(['message' => "Insufficient quota for ticket type: {$ticketType->name}. Available: {$ticketType->quota}."], 400);
                }

                $seatIds = array_values(array_unique($item['seat_ids'] ?? []));
                if (!empty($item['seat_ids']) && count($seatIds) !== $item['quantity']) {
                    DB::rollBack();
                    return response()->json(['message' => 'Number of selected seats must match the ticket quantity and cannot contain duplicates.'], 400);
                }
                if (!$event->has_seating && !empty($seatIds)) {
                    DB::rollBack();
                    return response()->json(['message' => 'Event ini menggunakan general admission dan tidak memiliki tempat duduk.'], 400);
                }

                // Decrement quota immediately to prevent race conditions
                $ticketType->quota -= $item['quantity'];
                $ticketType->save();

                // 3. Handle Seat Selection if seat_ids provided
                if (!empty($seatIds)) {
                    sort($seatIds, SORT_NUMERIC);

                    // Check if seats belong to the event venue and apply pessimistic lock
                    $seats = Seat::whereIn('id', $seatIds)->lockForUpdate()->get();
                    foreach ($seats as $seat) {
                        if ($seat->venue_id !== $event->venue_id) {
                            DB::rollBack();
                            return response()->json(['message' => "Seat {$seat->row}-{$seat->seat_number} does not belong to the event venue."], 400);
                        }
                    }

                    // Check if seats are already occupied for this event
                    $occupiedSeatIds = OrderDetail::whereHas('order', function($q) use ($event) {
                        $q->where('event_id', $event->id)
                          ->whereIn('status', ['pending', 'completed']);
                    })
                    ->whereIn('seat_id', $seatIds)
                    ->pluck('seat_id')
                    ->toArray();

                    if (!empty($occupiedSeatIds)) {
                        $occupiedSeats = Seat::whereIn('id', $occupiedSeatIds)->get()->map(function($s) {
                            return "{$s->section} - {$s->row}{$s->seat_number}";
                        })->implode(', ');
                        DB::rollBack();
                        return response()->json(['message' => "The following seats are already reserved: {$occupiedSeats}."], 400);
                    }

                    // Apply Cache Lock for selected seats to prevent race conditions during insert
                    foreach ($seatIds as $seatId) {
                        $lockKey = "seat_lock_event_{$event->id}_seat_{$seatId}";
                        $lock = Cache::lock($lockKey, 10); // Lock for 10 seconds
                        
                        if (!$lock->get()) {
                            // Failed to acquire lock, meaning someone else is currently booking it
                            foreach ($acquiredLocks as $acquiredLock) {
                                $acquiredLock->release();
                            }
                            DB::rollBack();
                            return response()->json(['message' => "One or more selected seats are currently being processed by another user. Please try again."], 409);
                        }
                        $acquiredLocks[] = $lock;
                    }
                }

                // Prepare order details data
                for ($i = 0; $i < $item['quantity']; $i++) {
                    $seatId = isset($seatIds[$i]) ? $seatIds[$i] : null;
                    $orderDetailsToCreate[] = [
                        'ticket_type_id' => $ticketType->id,
                        'seat_id' => $seatId,
                        'price' => $ticketType->price,
                    ];
                    $totalPrice += $ticketType->price;
                }
            }

            // Create Order
            $order = Order::create([
                'user_id' => $userId,
                'event_id' => $event->id,
                'total' => $totalPrice,
                'status' => 'pending',
            ]);

            // Save Order Details
            foreach ($orderDetailsToCreate as $detail) {
                $order->orderDetails()->create($detail);
            }

            DB::commit();

            foreach ($acquiredLocks as $lock) {
                $lock->release();
            }

            return response()->json([
                'message' => 'Order created successfully. Please proceed to payment.',
                'order' => $order->load(['orderDetails.ticketType', 'orderDetails.seat', 'event.venue'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            foreach ($acquiredLocks as $lock) {
                $lock->release();
            }
            
            return response()->json(['message' => 'Failed to create order: ' . $e->getMessage()], 500);
        } finally {
            optional($checkoutLock)->release();
        }
    }

    /**
     * Display a specific order details.
     */
    public function show(string $id, Request $request)
    {
        $order = Order::with(['event.venue', 'orderDetails.ticketType', 'orderDetails.seat', 'payment'])
            ->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        // Authorize: customer must own the order, or user is admin/organizer
        $user = $request->user();
        if ($user->role === 'customer' && $order->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'order' => $order
        ]);
    }
}
