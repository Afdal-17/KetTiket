<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('social_provider')->nullable()->after('avatar');
            $table->string('social_id')->nullable()->after('social_provider');
            $table->index(['social_provider', 'social_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['users_social_provider_social_id_index']);
            $table->dropColumn(['social_provider', 'social_id']);
        });
    }
};
