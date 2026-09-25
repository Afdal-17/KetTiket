<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organizer;
use App\Models\Venue;
use App\Models\Seat;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketBackendTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Run all migrations and seed before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Seed the database to get initial users, venue, and events
        $this->seed();
    }

    /**
     * Test authentication flow.
     */
    public function test_user_authentication_flow(): void
    {
        // 1. Test Customer Register
        $response = $this->postJson('/api/register', [
            'name' => 'Alice Customer',
            'email' => 'alice@customer.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'customer'
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['access_token', 'user']);

        // 2. Test Login
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'alice@customer.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertStatus(200)
                      ->assertJsonStructure(['access_token']);

        $token = $loginResponse->json('access_token');

        // 3. Test Profile
        $profileResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
                                 ->getJson('/api/profile');

        $profileResponse->assertStatus(200)
                        ->assertJsonPath('user.email', 'alice@customer.com');

        // 4. Test Logout
        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
                               ->postJson('/api/logout');

        $logoutResponse->assertStatus(200);
    }

    public function test_register_rejects_admin_role_assignment(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Mallory Admin',
            'email' => 'mallory@admin.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'mallory@admin.test']);
    }

    public function test_login_rejects_social_password_bypass(): void
    {
        User::create([
            'name' => 'Social User',
            'email' => 'social@example.com',
            'password' => bcrypt('real-password-123'),
            'role' => 'customer',
            'status' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'social@example.com',
            'password' => 'social_abc123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $user = User::create([
            'name' => 'Webhook Tester',
            'email' => 'webhook@example.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
            'status' => true,
        ]);

        $organizer = Organizer::create([
            'user_id' => $user->id,
            'company_name' => 'Webhook Events',
            'phone' => '081234567890',
            'verified' => true,
        ]);

        $venue = Venue::create([
            'name' => 'Webhook Venue',
            'address' => 'Jl. Webhook No. 1',
            'capacity' => 100,
        ]);

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'venue_id' => $venue->id,
            'title' => 'Webhook Test Event',
            'description' => 'Minimal event for webhook test',
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'status' => 'published',
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'total' => 200000,
            'status' => 'pending',
        ]);

        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'GoPay',
            'transaction_id' => 'TX-WEBHOOK-001',
            'status' => 'pending',
        ]);

        $payload = [
            'transaction_id' => 'TX-WEBHOOK-001',
            'status' => 'success',
        ];

        $validSignature = hash_hmac('sha256', 'TX-WEBHOOK-001:success', env('PAYMENT_WEBHOOK_SECRET', 'kettiket-local-secret'));

        $this->withHeader('X-Payment-Signature', 'invalid-signature')
            ->postJson('/api/webhook/payment', $payload)
            ->assertStatus(403);

        $this->withHeader('X-Payment-Signature', $validSignature)
            ->postJson('/api/webhook/payment', $payload)
            ->assertStatus(200);
    }

    /**
     * Test full End-to-End ticketing scenario.
     */
    public function test_end_to_end_ticketing_scenario(): void
    {
        // --- STEP 1: ADMIN OPERATIONS ---
        // Admin logins
        $adminUser = User::where('email', 'admin@kettiket.com')->first();
        if (!$adminUser) {
            $adminUser = User::create([
                'name' => 'Admin Test',
                'email' => 'admin@kettiket.com',
                'password' => bcrypt('password123'),
                'role' => 'admin',
            ]);
        }

        $adminLogin = $this->postJson('/api/login', [
            'email' => 'admin@kettiket.com',
            'password' => 'password123',
        ]);
        $adminLogin->assertStatus(200);
        $adminToken = $adminLogin->json('access_token');

        // Admin views dashboard
        $dashboard = $this->withoutExceptionHandling()
                          ->withHeader('Authorization', 'Bearer ' . $adminToken)
                          ->withHeader('Accept', 'application/json')
                          ->getJson('/api/admin/dashboard');
        
        file_put_contents('debug_dump.json', $dashboard->getContent());
        $dashboard->assertStatus(200)
                  ->assertJsonStructure(['metrics' => ['user_stats', 'total_sales', 'upcoming_events_count']]);

        // --- STEP 2: ORGANIZER REGISTRATION & VERIFICATION ---
        $regOrg = $this->postJson('/api/register', [
            'name' => 'Bob Organizer',
            'email' => 'bob@organizer.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'organizer',
            'company_name' => 'Bob Events Ltd',
            'phone' => '0899999999'
        ]);
        $regOrg->assertStatus(201);
        
        $user = User::where('email', 'bob@organizer.com')->first();
        $user->role = 'organizer';
        $user->save();
        
        $org = Organizer::create([
            'user_id' => $user->id,
            'company_name' => 'Bob Events Ltd',
            'phone' => '0899999999',
            'verified' => true
        ]);
        $orgId = $org->id;

        // --- STEP 3: ORGANIZER FLOW (CREATE VENUE, SEATS, EVENT, TICKET TYPE) ---
        // Organizer logins
        $orgLogin = $this->postJson('/api/login', [
            'email' => 'bob@organizer.com',
            'password' => 'password123',
        ]);
        $orgToken = $orgLogin->json('access_token');

 // Organizer creates venue
        $venueRes = $this->withHeader('Authorization', 'Bearer ' . $orgToken)
                          ->postJson('/api/venues', [
                              'name' => 'Convention Center Hall A',
                              'address' => 'Sudirman Central Business District, Jakarta',
                              'capacity' => 200
                          ]);
        $venueRes->assertStatus(201);
        $venueId = $venueRes->json('venue.id');

        // Organizer bulk generates seats for that venue
        $seatsRes = $this->withHeader('Authorization', 'Bearer ' . $orgToken)
                           ->postJson("/api/venues/{$venueId}/seats", [
                               'section' => 'VIP',
                               'rows' => ['A', 'B'],
                               'seat_count' => 3 // generates A-Seat 1 to 3 and B-Seat 1 to 3 (6 seats total)
                           ]);
        $seatsRes->assertStatus(201);
        $this->assertEquals(6, count($seatsRes->json('seats')));
        $firstSeatId = $seatsRes->json('seats.0.id');

        // Organizer creates event
        $eventRes = $this->withHeader('Authorization', 'Bearer ' . $orgToken)
                          ->postJson('/api/events', [
                              'venue_id' => $venueId,
                              'location_name' => 'Convention Center Hall A, Jakarta',
                              'title' => 'Symphony Concert 2026',
                              'description' => 'A magical evening of orchestral masterpieces.',
                              'start_date' => now()->addDays(10)->toDateTimeString(),
                              'end_date' => now()->addDays(10)->addHours(3)->toDateTimeString(),
                              'status' => 'published'
                          ]);

        $eventRes->assertStatus(201);
        $eventId = $eventRes->json('event.id');

        // Organizer adds ticket type to event
        $ttRes = $this->withHeader('Authorization', 'Bearer ' . $orgToken)
                      ->postJson("/api/events/{$eventId}/ticket-types", [
                          'name' => 'VIP Pass',
                          'price' => 2000000.00,
                          'quota' => 5
                      ]);
        $ttRes->assertStatus(201);
        $ticketTypeId = $ttRes->json('ticket_type.id');

        // --- STEP 4: CUSTOMER FLOW (BROWSE, ORDER, PAY, GET TICKETS) ---
        // Customer registers
        $custReg = $this->postJson('/api/register', [
            'name' => 'Charlie Customer',
            'email' => 'charlie@customer.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'customer'
        ]);
        $custToken = $custReg->json('access_token');

        // Customer browses events (search query)
        $browseRes = $this->getJson('/api/events?search=Symphony');
        $browseRes->assertStatus(200);
        $this->assertNotEmpty($browseRes->json('events'));

        // Customer views event details
        $detailRes = $this->getJson("/api/events/{$eventId}");
        $detailRes->assertStatus(200)
                  ->assertJsonPath('event.title', 'Symphony Concert 2026');

        // Customer gets seats map to check availability
        $seatsMapRes = $this->getJson("/api/venues/{$venueId}/seats?event_id={$eventId}");
        $seatsMapRes->assertStatus(200);

        // Customer checks out (Creates pending order)
        $orderRes = $this->withHeader('Authorization', 'Bearer ' . $custToken)
                         ->postJson('/api/orders', [
                             'event_id' => $eventId,
                             'items' => [
                                 [
                                     'ticket_type_id' => $ticketTypeId,
                                     'quantity' => 1,
                                     'seat_ids' => [$firstSeatId]
                                 ]
                             ]
                         ]);
        $orderRes->assertStatus(201)
                 ->assertJsonPath('order.status', 'pending')
                 ->assertJsonPath('order.total', '2000000.00');
        $orderId = $orderRes->json('order.id');

        // Customer pays for order
        $payRes = $this->withHeader('Authorization', 'Bearer ' . $custToken)
                       ->postJson("/api/orders/{$orderId}/pay", [
                           'gateway' => 'GoPay',
                       ]);
        $txId = $payRes->json('transaction_id');

        // Simulate Webhook success from payment gateway
        $signature = hash_hmac('sha256', $txId . ':success', env('PAYMENT_WEBHOOK_SECRET', 'kettiket-local-secret'));

        $webhookRes = $this->withHeader('X-Payment-Signature', $signature)
            ->postJson('/api/webhook/payment', [
                'transaction_id' => $txId,
                'status' => 'success'
            ]);
        $webhookRes->assertStatus(200);

        // Fetch order/tickets again
        $payRes = $this->withHeader('Authorization', 'Bearer ' . $custToken)
                       ->getJson("/api/orders/{$orderId}");
        
        $ticketCode = Ticket::whereHas('orderDetail', function($q) use ($orderId) {
            $q->where('order_id', $orderId);
        })->first()->qr_code;

        // Customer retrieves active tickets
        $ticketsRes = $this->withHeader('Authorization', 'Bearer ' . $custToken)
                           ->getJson('/api/tickets');
        $ticketsRes->assertStatus(200);
        $this->assertEquals(1, count($ticketsRes->json('tickets')));

        // --- STEP 5: SCANNER SCAN TICKETS FLOW ---
        // Scanner logs in
        $scannerLogin = $this->postJson('/api/login', [
            'email' => 'scanner@kettiket.com',
            'password' => 'password123',
        ]);
        $scannerToken = $scannerLogin->json('access_token');

        // 1st Scan: Approved
        $scan1 = $this->withHeader('Authorization', 'Bearer ' . $scannerToken)
                      ->postJson('/api/tickets/scan', [
                          'code' => $ticketCode
                      ]);
        $scan1->assertStatus(200)
              ->assertJsonPath('status', 'approved')
              ->assertJsonPath('message', 'Access Granted - Tiket Valid & Berhasil Masuk!');

        // 2nd Scan: Rejected (already used)
        $scan2 = $this->withHeader('Authorization', 'Bearer ' . $scannerToken)
                      ->postJson('/api/tickets/scan', [
                          'code' => $ticketCode
                      ]);
        $scan2->assertStatus(400)
              ->assertJsonPath('status', 'rejected')
              ->assertJsonPath('message', 'Access Denied - Tiket sudah pernah digunakan (sudah di-scan sebelumnya).');

        // Invalid Scan: Rejected (not exists)
        $scan3 = $this->withHeader('Authorization', 'Bearer ' . $scannerToken)
                      ->postJson('/api/tickets/scan', [
                          'code' => 'INVALIDCODE123'
                      ]);
        $scan3->assertStatus(404)
              ->assertJsonPath('status', 'rejected')
              ->assertJsonPath('message', 'Access Denied - Kode presentasi atau barcode tidak valid.');
    }
}
