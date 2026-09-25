<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Organizer;
use App\Models\Venue;
use App\Models\Seat;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@kettiket.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'status' => true,
            ]
        );
        $admin->update([
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => true,
        ]);

        // 2. Create Organizer User and profile
        $organizerUser = User::firstOrCreate(
            ['email' => 'organizer@kettiket.com'],
            [
                'name' => 'John Organizer',
                'password' => Hash::make('password123'),
                'role' => 'organizer',
                'status' => true,
            ]
        );
        $organizerUser->update([
            'password' => Hash::make('password123'),
            'role' => 'organizer',
            'status' => true,
        ]);

        $organizer = Organizer::firstOrCreate(
            ['user_id' => $organizerUser->id],
            [
                'company_name' => 'Maju Jaya Entertainment',
                'phone' => '081234567890',
                'verified' => true,
            ]
        );

        // 3. Create Scanner User
        $scannerUser = User::firstOrCreate(
            ['email' => 'scanner@kettiket.com'],
            [
                'name' => 'Sam Scanner',
                'password' => Hash::make('password123'),
                'role' => 'scanner',
                'status' => true,
            ]
        );
        $scannerUser->update([
            'password' => Hash::make('password123'),
            'role' => 'scanner',
            'status' => true,
        ]);

        // 4. Create Customer User
        $customerUser = User::firstOrCreate(
            ['email' => 'customer@kettiket.com'],
            [
                'name' => 'Candra Customer',
                'password' => Hash::make('password123'),
                'role' => 'customer',
                'status' => true,
            ]
        );
        $customerUser->update([
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => true,
        ]);

        // 5. Create Venues with Google Maps Coordinates
        $venue1 = Venue::firstOrCreate(
            ['name' => 'Gelora Bung Karno'],
            [
                'address' => 'Jl. Pintu Satu Senayan, Gelora, Tanah Abang, Jakarta Pusat',
                'capacity' => 77000,
                'city' => 'Jakarta Pusat',
                'latitude' => -6.2185768,
                'longitude' => 106.8017834,
                'google_maps_link' => 'https://maps.google.com/?q=-6.2185768,106.8017834',
            ]
        );
        $venue1->update([
            'latitude' => -6.2185768,
            'longitude' => 106.8017834,
            'google_maps_link' => 'https://maps.google.com/?q=-6.2185768,106.8017834',
        ]);

        $venue2 = Venue::firstOrCreate(
            ['name' => 'Jakarta International Stadium (JIS)'],
            [
                'address' => 'Papanggo, Tanjung Priok, Jakarta Utara',
                'capacity' => 82000,
                'city' => 'Jakarta Utara',
                'latitude' => -6.124618,
                'longitude' => 106.858488,
                'google_maps_link' => 'https://maps.google.com/?q=-6.124618,106.858488',
            ]
        );

        $venue3 = Venue::firstOrCreate(
            ['name' => 'Indonesia Convention Exhibition (ICE) BSD'],
            [
                'address' => 'Jl. BSD Grand Boulevard No.1, Pagedangan, Tangerang, Banten',
                'capacity' => 10000,
                'city' => 'Tangerang',
                'latitude' => -6.300588,
                'longitude' => 106.637372,
                'google_maps_link' => 'https://maps.google.com/?q=-6.300588,106.637372',
            ]
        );

        // 6. Create Seats for Venue 1 if not exists
        if (Seat::where('venue_id', $venue1->id)->count() === 0) {
            // VIP Row A (Seat 1 - 5)
            for ($i = 1; $i <= 5; $i++) {
                Seat::create([
                    'venue_id' => $venue1->id,
                    'section' => 'VIP',
                    'row' => 'A',
                    'seat_number' => 'Seat ' . $i,
                ]);
            }
            // VIP Row B (Seat 1 - 5)
            for ($i = 1; $i <= 5; $i++) {
                Seat::create([
                    'venue_id' => $venue1->id,
                    'section' => 'VIP',
                    'row' => 'B',
                    'seat_number' => 'Seat ' . $i,
                ]);
            }
            // Festival Row F (Seat 1 - 10)
            for ($i = 1; $i <= 10; $i++) {
                Seat::create([
                    'venue_id' => $venue1->id,
                    'section' => 'Festival',
                    'row' => 'F',
                    'seat_number' => 'Standing ' . $i,
                ]);
            }
        }

        // 7. Create Event
        $event = Event::firstOrCreate(
            [
                'title' => 'Coldplay Music of the Spheres Jakarta',
                'organizer_id' => $organizer->id,
            ],
            [
                'venue_id' => $venue1->id,
                'description' => 'World Tour 2026 Live in Jakarta. Experience the spectacular show with vibrant lights and legendary hits.',
                'image' => '/images/sheila_on_7.jpeg',
                'start_date' => now()->addDays(5)->setHour(19)->setMinute(0)->setSecond(0),
                'end_date' => now()->addDays(5)->setHour(23)->setMinute(0)->setSecond(0),
                'status' => 'published',
                'latitude' => $venue1->latitude,
                'longitude' => $venue1->longitude,
                'google_maps_link' => $venue1->google_maps_link,
            ]
        );

        // 8. Create Ticket Types
        TicketType::firstOrCreate(
            [
                'event_id' => $event->id,
                'name' => 'VIP Pass',
            ],
            [
                'price' => 5000000.00,
                'quota' => 10,
            ]
        );

        TicketType::firstOrCreate(
            [
                'event_id' => $event->id,
                'name' => 'Festival Ticket',
            ],
            [
                'price' => 1500000.00,
                'quota' => 20,
            ]
        );
    }
}
