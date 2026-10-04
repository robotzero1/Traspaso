<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('month_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('calendar_month');
            $table->unsignedInteger('customers');
            $table->bigInteger('revenue_cents');
            $table->bigInteger('event_revenue_cents')->default(0);
            $table->bigInteger('cogs_cents');
            $table->bigInteger('staff_cents');
            $table->bigInteger('rent_cents');
            $table->bigInteger('utilities_cents');
            $table->bigInteger('marketing_cents');
            $table->bigInteger('other_cents');
            $table->bigInteger('taxes_cents');
            $table->bigInteger('profit_cents');
            $table->bigInteger('cash_after_cents');
            $table->json('day_parts');
            $table->json('events');
            $table->timestamps();

            $table->unique(['game_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('month_results');
    }
};
