<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\ScanLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    /**
     * Display a listing of the customer's tickets.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Retrieve tickets belonging to the logged-in customer
        $tickets = Ticket::with(['orderDetail.order.event.venue', 'orderDetail.ticketType.event.venue', 'orderDetail.seat'])
            ->whereHas('orderDetail.order', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->get()
            ->map(function($ticket) {
                return $this->formatTicket($ticket);
            });

        return response()->json([
            'tickets' => $tickets
        ]);
    }

    /**
     * Display details of a specific ticket.
     */
    public function show(string $id, Request $request)
    {
        $user = $request->user();

        $ticket = Ticket::with(['orderDetail.order.event.venue', 'orderDetail.ticketType', 'orderDetail.seat', 'scanLogs.scanner'])
            ->find($id);

        if (!$ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        // Authorize: check if user owns the ticket, or is scanner/organizer/admin
        if ($user->role === 'customer' && $ticket->orderDetail->order->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'ticket' => $this->formatTicket($ticket)
        ]);
    }

    public function present(string $id, Request $request)
    {
        $ticket = Ticket::with(['orderDetail.order.event', 'orderDetail.ticketType.event'])->find($id);
        if (!$ticket || $ticket->orderDetail->order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }
        if ($ticket->status !== 'active') {
            return response()->json(['message' => 'Ticket tidak dapat ditampilkan karena statusnya sudah ' . $ticket->status . '.'], 400);
        }

        $ticket->update([
            'scan_requested_at' => now(),
            'scan_token' => Str::random(64),
        ]);

        // Masa berlaku tiket mengikuti waktu event/konser — bukan hitungan menit
        $eventEnd = $this->ticketEventEnd($ticket);

        return response()->json([
            'message' => 'Barcode siap ditunjukkan selama 35 detik. Masa berlaku tiket mengikuti waktu event.',
            'available_at' => now()->toIso8601String(),
            'expires_at' => $eventEnd ? $eventEnd->toIso8601String() : null,
            'scan_token' => $ticket->scan_token,
            'qr_code' => $ticket->qr_code,
            'barcode' => $ticket->barcode,
        ]);
    }

    /**
     * Scan a ticket by QR code or Barcode (Scanner/Organizer/Admin only).
     */
    public function scan(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();
        if (!in_array($user->role, ['scanner', 'organizer', 'admin'])) {
            return response()->json(['message' => 'Unauthorized. Only scanners, organizers, or admins can scan tickets.'], 403);
        }

        $code = $request->code;

        DB::beginTransaction();
        /** @var \App\Models\Ticket $ticket */
        $ticket = Ticket::with(['orderDetail.order.user', 'orderDetail.order.event.venue', 'orderDetail.order.payment', 'orderDetail.ticketType.event.venue', 'orderDetail.seat'])
            ->where('scan_token', $code)
            ->orWhere('qr_code', $code)
            ->orWhere('barcode', $code)
            ->lockForUpdate()
            ->first();

        // 1. Check if ticket exists
        if (!$ticket) {
            DB::rollBack();
            return response()->json([
                'status' => 'rejected',
                'message' => 'Access Denied - Kode presentasi atau barcode tidak valid.'
            ], 404);
        }

        // 2. Cek kedaluwarsa mengikuti WAKTU EVENT/KONSER: tiket berlaku sampai konser selesai,
        //    bukan kedaluwarsa beberapa menit setelah barcode dibuat/ditampilkan.
        $eventEnd = $this->ticketEventEnd($ticket);
        if ($eventEnd && now()->gt($eventEnd)) {
            DB::rollBack();
            return response()->json([
                'status' => 'rejected',
                'message' => 'Access Denied - Tiket kedaluwarsa karena event sudah berakhir pada ' . $eventEnd->format('d/m/Y H:i') . '.',
                'ticket' => $this->formatTicket($ticket)
            ], 400);
        }

        // 3. Check if already used
        if ($ticket->status === 'used') {
            DB::rollBack();
            return response()->json([
                'status' => 'rejected',
                'message' => 'Access Denied - Tiket sudah pernah digunakan (sudah di-scan sebelumnya).',
                'ticket' => $this->formatTicket($ticket)
            ], 400);
        }

        // 4. Check if cancelled
        if ($ticket->status === 'cancelled') {
            DB::rollBack();
            return response()->json([
                'status' => 'rejected',
                'message' => 'Access Denied - Tiket telah dibatalkan.',
                'ticket' => $this->formatTicket($ticket)
            ], 400);
        }

        // 5. Ticket is valid -> update status and allow entry
        $ticket->status = 'used';
        $ticket->scan_token = null;
        $ticket->save();

        // Log the scan event
        ScanLog::create([
            'ticket_id' => $ticket->id,
            'scanner_id' => $user->id,
            'scan_time' => now(),
        ]);

        DB::commit();

        return response()->json([
            'status' => 'approved',
            'message' => 'Access Granted - Tiket Valid & Berhasil Masuk!',
            'scanned_at' => now()->toIso8601String(),
            'scanner_name' => $user->name,
            'ticket' => $this->formatTicket($ticket)
        ]);
    }

    public function scanHistory(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role, ['scanner', 'organizer', 'admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $logs = ScanLog::with(['ticket.orderDetail.order.user', 'ticket.orderDetail.ticketType.event', 'ticket.orderDetail.seat', 'scanner'])
            ->orderBy('scan_time', 'desc')
            ->limit(50)
            ->get();

        // Ringkasan untuk kartu statistik halaman scanner
        $summary = [
            'total_checkins' => ScanLog::count(),
            'today_checkins' => ScanLog::whereDate('scan_time', today())->count(),
            'unique_customers' => DB::table('scan_logs')
                ->join('tickets', 'tickets.id', '=', 'scan_logs.ticket_id')
                ->join('order_details', 'order_details.id', '=', 'tickets.order_detail_id')
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->distinct()
                ->count('orders.user_id'),
        ];

        return response()->json([
            'scan_logs' => $logs,
            'summary' => $summary,
        ]);
    }

    /**
     * Waktu berakhirnya masa berlaku tiket = akhir event/konser.
     * Mengikuti end_date event (fallback start_date); null bila data event tidak tersedia
     * (dalam hal itu tiket tidak dianggap kedaluwarsa).
     */
    private function ticketEventEnd($ticket): ?Carbon
    {
        $event = $ticket->orderDetail?->ticketType?->event
            ?? $ticket->orderDetail?->order?->event;

        if (!$event) {
            return null;
        }

        $end = $event->end_date ?: $event->start_date;

        return $end ? Carbon::parse($end) : null;
    }

    /**
     * Format ticket response to include helpful helper attributes.
     */
    private function formatTicket($ticket)
    {
        // Add dynamic helper links for rendering QR/Barcode
        $ticket->qr_code_image_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($ticket->qr_code);
        $ticket->barcode_image_url = 'https://barcode.tec-it.com/barcode.ashx?data=' . urlencode($ticket->barcode) . '&code=Code128&translate-esc=true';
        
        return $ticket;
    }
}
