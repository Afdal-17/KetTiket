<?php
// scripts/simulate_checkout.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Str;

echo "=== Simulate Checkout Script ===\n";

try {
    // Create or find a test user
    $user = User::first();
    if (!$user) {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test+' . time() . '@example.local',
            'password' => bcrypt('password123'),
            'role' => 'customer',
            'status' => true,
        ]);
        echo "Created user id: {$user->id}\n";
    } else {
        echo "Using user id: {$user->id}\n";
    }

    // Ensure organizer exists for the event
    $organizer = $user->organizer;
    if (!$organizer) {
        $organizer = App\Models\Organizer::create([
            'user_id' => $user->id,
            'company_name' => 'Test Organizer ' . time(),
            'phone' => '081234567890',
            'verified' => true,
        ]);
        echo "Created organizer id: {$organizer->id}\n";
    } else {
        echo "Using organizer id: {$organizer->id}\n";
    }

    // Ensure a venue exists
    $venueClass = App\Models\Venue::class;
    $venue = $venueClass::first();
    if (!$venue) {
        $venue = $venueClass::create([
            'name' => 'Test Venue ' . time(),
            'address' => 'Jl. Test 1',
            'city' => 'TestCity',
            'state' => 'TS',
            'country' => 'ID',
            'postal_code' => '12345',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'capacity' => 1000,
        ]);
        echo "Created venue id: {$venue->id}\n";
    } else {
        echo "Using venue id: {$venue->id}\n";
    }

    // Create a lightweight event
    $event = Event::create([
        'organizer_id' => $organizer->id,
        'venue_id' => $venue->id,
        'title' => 'Simulated Event ' . time(),
        'description' => 'Test event for Midtrans checkout',
        'start_date' => now(),
        'end_date' => now()->addHour(),
        'status' => 'published',
    ]);
    echo "Created event id: {$event->id}\n";

    // Create ticket type
    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'General Admission',
        'price' => 10000,
        'quota' => 100,
    ]);
    echo "Created ticket type id: {$ticketType->id}\n";

    // Create order
    $order = Order::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'total' => 10000,
        'status' => 'pending',
    ]);
    echo "Created order id: {$order->id}\n";

    // Create order detail
    $detail = OrderDetail::create([
        'order_id' => $order->id,
        'ticket_type_id' => $ticketType->id,
        'seat_id' => null,
        'price' => 10000,
    ]);
    echo "Created order detail id: {$detail->id}\n";

    // Prepare request
    $request = Request::create('/api/orders/' . $order->id . '/pay', 'POST', ['gateway' => 'midtrans']);
    $request->setUserResolver(function() use ($user) { return $user; });

    // Call controller
    try {
        $controller = new PaymentController();
        $response = $controller->pay($request, (string)$order->id);

        echo "Controller response type: " . get_class($response) . "\n";
        if (method_exists($response, 'getStatusCode')) {
            echo "Status: " . $response->getStatusCode() . "\n";
        }
        if (method_exists($response, 'getContent')) {
            echo "Body: " . $response->getContent() . "\n";
        }
    } catch (Throwable $t) {
        echo "Controller threw: " . get_class($t) . ": " . $t->getMessage() . "\n";
        echo $t->getTraceAsString() . "\n";
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "=== Done ===\n";