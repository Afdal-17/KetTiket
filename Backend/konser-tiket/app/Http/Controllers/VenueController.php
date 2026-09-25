<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Models\Seat;
use App\Models\OrderDetail;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    /**
     * Display a listing of venues.
     */
    public function index()
    {
        $venues = Venue::all();
        return response()->json([
            'venues' => $venues
        ]);
    }

    /**
     * Store a newly created venue.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'capacity' => 'required|integer|min:1',
            'google_maps_link' => 'nullable|string',
            'image' => 'nullable|string',
        ]);

        $venue = Venue::create([
            'name' => $request->name,
            'address' => $request->address,
            'capacity' => $request->capacity,
            'google_maps_link' => $request->google_maps_link,
            'image' => $request->image,
        ]);

        return response()->json([
            'message' => 'Venue created successfully',
            'venue' => $venue
        ], 201);
    }

    /**
     * Bulk generate seats for a venue section.
     */
    public function generateSeats(Request $request, string $venueId)
    {
        $venue = Venue::find($venueId);
        if (!$venue) {
            return response()->json(['message' => 'Venue not found.'], 404);
        }

        $request->validate([
            'section' => 'required|string|max:255', // e.g., 'VIP', 'Tribune A'
            'rows' => 'required|array', // e.g., ['A', 'B', 'C']
            'rows.*' => 'required|string',
            'seat_count' => 'required|integer|min:1', // seats per row, generates 1 to seat_count
        ]);

        $createdSeats = [];
        foreach ($request->rows as $row) {
            for ($num = 1; $num <= $request->seat_count; $num++) {
                $seat = Seat::firstOrCreate([
                    'venue_id' => $venue->id,
                    'section' => $request->section,
                    'row' => $row,
                    'seat_number' => 'Seat ' . $num,
                ]);
                $createdSeats[] = $seat;
            }
        }

        return response()->json([
            'message' => count($createdSeats) . ' seats generated successfully.',
            'seats' => $createdSeats
        ], 201);
    }

    /**
     * Get seat map for a venue, optionally showing availability for a specific event.
     */
    public function getSeats(Request $request, string $venueId)
    {
        $venue = Venue::find($venueId);
        if (!$venue) {
            return response()->json(['message' => 'Venue not found.'], 404);
        }

        $seats = Seat::where('venue_id', $venue->id)->get();
        $eventId = $request->query('event_id');
        if ($eventId) {
            // Find seats already booked/ordered for this event (where order status is completed or pending)
            $bookedSeatIds = OrderDetail::whereHas('order', function($q) use ($eventId) {
                $q->where('event_id', $eventId)
                  ->whereIn('status', ['pending', 'completed']);
            })
            ->whereNotNull('seat_id')
            ->pluck('seat_id')
            ->toArray();

            $seats = $seats->map(function($seat) use ($bookedSeatIds) {
                $seat->is_available = !in_array($seat->id, $bookedSeatIds);
                return $seat;
            });
        }

        return response()->json([
            'venue' => $venue,
            'seats' => $seats
        ]);
    }
}
