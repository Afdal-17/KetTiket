<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('scan_requested_at')->nullable()->after('status');
            $table->string('scan_token', 64)->nullable()->unique()->after('scan_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['scan_token']);
            $table->dropColumn(['scan_requested_at', 'scan_token']);
        });
    }
};