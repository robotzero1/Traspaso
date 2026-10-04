<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The business at the end of each month (month 0 = the day it was
        // bought), and the decisions that produced it.
        Schema::create('game_business_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->decimal('reputation', 5, 2);
            $table->unsignedSmallInteger('staff_count');
            $table->decimal('staff_morale', 5, 2);
            $table->decimal('equipment_health', 5, 2);
            $table->unsignedSmallInteger('equipment_age_months');
            $table->decimal('stock_quality', 5, 2);
            $table->json('modifiers');
            $table->json('pending_events');
            $table->json('decisions')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_business_states');
    }
};
