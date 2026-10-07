<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per game per day traded (SPEC §11, data model additions).
        Schema::create('day_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // The game month the day belongs to (from 1).
            $table->unsignedSmallInteger('month');
            $table->boolean('open');
            $table->string('weather');
            $table->boolean('terrace_usable');
            $table->unsignedInteger('customers');
            $table->bigInteger('revenue_cents');
            $table->bigInteger('cogs_cents');
            $table->bigInteger('event_cost_cents')->default(0);
            // Cash at the end of the day; the month's bills go out on its last day.
            $table->bigInteger('cash_after_cents');
            $table->json('day_parts');
            $table->json('events');
            $table->timestamps();

            $table->unique(['game_id', 'date']);
            $table->index(['game_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_results');
    }
};
