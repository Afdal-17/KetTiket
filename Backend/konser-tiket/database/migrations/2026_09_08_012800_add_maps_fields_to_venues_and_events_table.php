<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            if (!Schema::hasColumn('venues', 'city')) {
                $table->string('city')->nullable();
            }
            if (!Schema::hasColumn('venues', 'state')) {
                $table->string('state')->nullable();
            }
            if (!Schema::hasColumn('venues', 'country')) {
                $table->string('country')->nullable();
            }
            if (!Schema::hasColumn('venues', 'postal_code')) {
                $table->string('postal_code')->nullable();
            }
            if (!Schema::hasColumn('venues', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable();
            }
            if (!Schema::hasColumn('venues', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable();
            }
            if (!Schema::hasColumn('venues', 'google_maps_link')) {
                $table->text('google_maps_link')->nullable();
            }
            if (!Schema::hasColumn('venues', 'image')) {
                $table->string('image')->nullable();
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable();
            }
            if (!Schema::hasColumn('events', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable();
            }
            if (!Schema::hasColumn('events', 'google_maps_link')) {
                $table->text('google_maps_link')->nullable();
            }
            if (!Schema::hasColumn('events', 'ticket_types_data')) {
                $table->json('ticket_types_data')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn([
                'city', 'state', 'country', 'postal_code',
                'latitude', 'longitude', 'google_maps_link', 'image'
            ]);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'latitude', 'longitude', 'google_maps_link', 'ticket_types_data'
            ]);
        });
    }
};
