<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per device that turned on notifications (SPEC §11).
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 500)->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('content_encoding')->default('aes128gcm');
            $table->timestamps();
        });

        // What each player wants to hear about.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_daily_results')->default(true);
            $table->boolean('notify_events')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_daily_results', 'notify_events']);
        });
    }
};
