<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('status');
            $table->date('date_of_birth')->nullable()->after('phone');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('date_of_birth');
            $table->text('avatar')->nullable()->after('gender');
            $table->text('bio')->nullable()->after('avatar');
            $table->string('address')->nullable()->after('bio');
            $table->string('city')->nullable()->after('address');
            $table->string('state')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('state');
            $table->string('country')->default('Indonesia')->after('postal_code');
            $table->string('emergency_contact_name')->nullable()->after('country');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('preferred_language')->default('id')->after('emergency_contact_phone');
            $table->string('timezone')->default('Asia/Jakarta')->after('preferred_language');
            $table->json('notification_preferences')->nullable()->after('timezone');
            $table->timestamp('last_login_at')->nullable()->after('notification_preferences');
            $table->integer('login_count')->default(0)->after('last_login_at');
            $table->enum('profile_visibility', ['public', 'private', 'organizer_only'])->default('public')->after('login_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'date_of_birth', 'gender', 'avatar', 'bio',
                'address', 'city', 'state', 'postal_code', 'country',
                'emergency_contact_name', 'emergency_contact_phone',
                'preferred_language', 'timezone', 'notification_preferences',
                'last_login_at', 'login_count', 'profile_visibility'
            ]);
        });
    }
};
