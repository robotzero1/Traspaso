<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SPEC §5 "events". An event type happens at most once a month.
        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->string('type');
            $table->json('payload');
            $table->json('choices');
            $table->string('choice')->nullable();
            // The month the choice took effect.
            $table->unsignedTinyInteger('resolved_month')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'month', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_events');
    }
};
