<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('market');
            $table->bigInteger('seed');
            $table->date('start_date');
            $table->bigInteger('starting_capital_cents');
            $table->bigInteger('cash_cents');
            // The month about to be played, 1–12; 13 once all are played.
            $table->unsignedTinyInteger('current_month')->default(1);
            $table->string('status')->default('active');
            // Set once a business is bought (foreign key added with the businesses table).
            $table->unsignedBigInteger('business_id')->nullable();
            $table->bigInteger('deposit_cents')->default(0);
            // The decisions for the month about to be played.
            $table->json('decisions')->nullable();
            $table->bigInteger('sold_for_cents')->nullable();
            $table->bigInteger('final_net_worth_cents')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
