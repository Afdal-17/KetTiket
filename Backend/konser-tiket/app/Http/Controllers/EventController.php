<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * Display a listing of events (Public browsing & searching).
     */
    public function index(Request $request)
    {
        $query = Event::with(['venue', 'organizer']);

        // Only show published events for general users, unless role is admin or organizer viewing their own
        $user = $request->user('sanctum');
        if (!$user || !in_array($user->role, ['admin', 'organizer'])) {
            $query->where('status', 'published');
        } elseif ($user && $user->role === 'organizer') {
            // Organizers see published events OR their own draft/cancelled events
            $organizerId = $user->organizer ? $user->organizer->id : 0;
            $query->where(function($q) use ($organizerId) {
                $q->where('status', 'published')
                  ->orWhere('organizer_id', $organizerId);
            });
        }

        // Search by title
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Filter by date
        if ($request->has('date')) {
            $query->whereDate('start_date', $request->date);
        }

        $events = $query->orderBy('start_date', 'asc')->get();

        return response()->json([
            'events' => $events
        ]);
    }

    /**
     * Store a newly created event (Organizer only) with enhanced features.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'organizer') {
            return response()->json(['message' => 'Only organizers can create events.'], 403);
        }

        $organizer = $user->organizer;
        if (!$organizer || !$organizer->verified) {
            return response()->json(['message' => 'Organizer account must be verified by admin before creating events.'], 403);
        }

        $request->validate([
            'location_name' => 'required|string|max:255',
            'has_seating' => 'sometimes|boolean',
            'seat_sections' => 'nullable|array',
            'seat_sections.*.section' => 'required_with:seat_sections|string|max:100',
            'seat_sections.*.quota' => 'required_with:seat_sections|integer|min:1',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'nullable|string',
            'start_date' => 'required|date|after:now',
            'end_date' => 'required|date|after:start_date',
            'status' => 'nullable|string|in:draft,published',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'google_maps_link' => 'nullable|string',
            'ticket_types' => 'nullable|array',
            'ticket_types.*.name' => 'required|string|max:255',
            'ticket_types.*.price' => 'required|numeric|min:0',
            'ticket_types.*.quota' => 'required|integer|min:1',
        ]);

        $locationName = trim((string) ($request->input('location_name') ?? ''));
        $mapLink = $request->input('google_maps_link');
        if (!$mapLink && $locationName !== '') {
            $mapLink = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($locationName);
        }

        $venueId = $request->input('venue_id');
        if (!$venueId && $locationName !== '') {
            $venue = \App\Models\Venue::firstOrCreate(
                ['name' => $locationName],
                [
                    'address' => $locationName,
                    'city' => 'Custom',
                    'capacity' => 10000,
                    'latitude' => $request->input('latitude'),
                    'longitude' => $request->input('longitude'),
                    'google_maps_link' => $mapLink,
                ]
            );
            $venueId = $venue->id;
        }

        $eventData = [
            'organizer_id' => $organizer->id,
            'venue_id' => $venueId ?: null,
            'location_name' => $locationName !== '' ? $locationName : null,
            'has_seating' => $request->boolean('has_seating', true),
            'title' => $request->title,
            'description' => $request->description,
            'image' => $request->image,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status ?? 'draft',
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'google_maps_link' => $mapLink,
            'ticket_types_data' => $request->ticket_types ?? [],
        ];

        $event = Event::create($eventData);

        if ($request->boolean('has_seating', true) && $request->has('seat_sections') && is_array($request->seat_sections)) {
            $venue = $event->venue;
            if ($venue) {
                foreach ($request->seat_sections as $secData) {
                    $secName = $secData['section'] ?? 'VIP';
                    $quota = (int) ($secData['quota'] ?? 50);
                    for ($i = 1; $i <= min($quota, 200); $i++) {
                        \App\Models\Seat::create([
                            'venue_id' => $venue->id,
                            'section' => $secName,
                            'row' => '1',
                            'seat_number' => (string) $i,
                        ]);
                    }
                }
            }
        }

        // Create ticket types if provided
        if ($request->has('ticket_types') && $request->ticket_types) {
            foreach ($request->ticket_types as $ticketData) {
                TicketType::create([
                    'event_id' => $event->id,
                    'name' => $ticketData['name'],
                    'price' => $ticketData['price'],
                    'quota' => $ticketData['quota'],
                ]);
            }
        }

        return response()->json([
            'message' => 'Event created successfully with Google Maps integration and ticket categories',
            'event' => $event->load(['venue', 'organizer', 'ticketTypes'])
        ], 201);
    }

    /**
     * Enhanced update method for organizer events with full CRUD support.
     */
    public function update(Request $request, string $id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $user = $request->user();

        // Check ownership or admin
        if ($user->role !== 'admin' && (!$user->organizer || $event->organizer_id !== $user->organizer->id)) {
            return response()->json(['message' => 'Unauthorized to update this event.'], 403);
        }

        $request->validate([
            'venue_id' => 'sometimes|nullable|exists:venues,id',
            'location_name' => 'sometimes|nullable|string|max:255',
            'has_seating' => 'sometimes|boolean',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'image' => 'nullable|string',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'status' => 'sometimes|required|string|in:draft,published,completed,cancelled',
            // Enhanced Google Maps fields
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'google_maps_link' => 'nullable|string',
            'venue_latitude' => 'sometimes|required|numeric|between:-90,90',
            'venue_longitude' => 'sometimes|required|numeric|between:-180,180',
            'ticket_types' => 'sometimes|array',
            'ticket_types.*.id' => 'sometimes|exists:ticket_types,id',
            'ticket_types.*.name' => 'sometimes|required|string|max:255',
            'ticket_types.*.price' => 'sometimes|required|numeric|min:0',
            'ticket_types.*.quota' => 'sometimes|required|integer|min:1',
        ]);

        // Prepare update data with Google Maps integration
        $updateData = $request->only([
            'venue_id', 'location_name', 'has_seating', 'title', 'description', 'image', 'start_date', 'end_date', 'status',
            'latitude', 'longitude', 'google_maps_link'
        ]);

        if ($request->has('location_name') && !$request->filled('google_maps_link')) {
            $updateData['google_maps_link'] = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($request->input('location_name'));
        }

        // Handle venue coordinates for map integration
        if ($request->has('venue_latitude') && $request->has('venue_longitude')) {
            $updateData['latitude'] = $request->venue_latitude;
            $updateData['longitude'] = $request->venue_longitude;
        }

        $event->update($updateData);

        // Handle ticket types updates
        if ($request->has('ticket_types')) {
            // Delete ticket types that are marked for removal (with id but empty data)
            foreach ($request->ticket_types as $ticketData) {
                if (isset($ticketData['id']) && (empty($ticketData['name']) || empty($ticketData['price']))) {
                    TicketType::where('id', $ticketData['id'])->delete();
                }
            }

            // Create or update ticket types
            foreach ($request->ticket_types as $ticketData) {
                if (!empty($ticketData['name']) && !empty($ticketData['price']) && !empty($ticketData['quota'])) {
                    if (isset($ticketData['id'])) {
                        $ticket = TicketType::find($ticketData['id']);
                        if ($ticket && ($ticket->event_id === $event->id)) {
                            $ticket->update([
                                'name' => $ticketData['name'],
                                'price' => $ticketData['price'],
                                'quota' => $ticketData['quota'],
                            ]);
                        }
                    } else {
                        TicketType::create([
                            'event_id' => $event->id,
                            'name' => $ticketData['name'],
                            'price' => $ticketData['price'],
                            'quota' => $ticketData['quota'],
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'message' => 'Event updated successfully with Google Maps integration',
            'event' => $event->load(['venue', 'organizer', 'ticketTypes'])
        ]);
    }

    /**
     * Display the specified event details with full Google Maps integration.
     */
    public function show(string $id)
    {
        $event = Event::with(['venue', 'organizer', 'ticketTypes'])->find($id);

        if (!$event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        return response()->json([
            'event' => $event
        ]);
    }

    /**
     * Remove the specified event.
     */
    public function destroy(Request $request, string $id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $user = $request->user();

        // Check ownership or admin
        if ($user->role !== 'admin' && (!$user->organizer || $event->organizer_id !== $user->organizer->id)) {
            return response()->json(['message' => 'Unauthorized to delete this event.'], 403);
        }

        $event->delete();

        return response()->json([
            'message' => 'Event deleted successfully'
        ]);
    }

    /**
     * Add ticket types to an event.
     */
    public function addTicketTypes(Request $request, string $id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $user = $request->user();

        if ($user->role !== 'admin' && (!$user->organizer || $event->organizer_id !== $user->organizer->id)) {
            return response()->json(['message' => 'Unauthorized to manage ticket types for this event.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'quota' => 'required|integer|min:1',
        ]);

        $ticketType = TicketType::create([
            'event_id' => $event->id,
            'name' => $request->name,
            'price' => $request->price,
            'quota' => $request->quota,
        ]);

        return response()->json([
            'message' => 'Ticket type added successfully',
            'ticket_type' => $ticketType
        ], 201);
    }
}
