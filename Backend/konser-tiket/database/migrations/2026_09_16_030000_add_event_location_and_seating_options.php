<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->change();
            $table->boolean('has_seating')->default(true)->after('venue_id');
            $table->string('location_name')->nullable()->after('venue_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['has_seating', 'location_name']);
        });
    }
};