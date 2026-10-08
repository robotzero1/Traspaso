<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Selling the café (SPEC §12): the listing, and the offers on it.
        Schema::create('sale_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('asking_cents');
            $table->boolean('agency')->default(false);
            $table->date('listed_on');
            $table->date('withdrawn_on')->nullable();
            $table->date('accepted_on')->nullable();
            $table->date('completes_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->unsignedBigInteger('price_cents')->nullable();
            // SaleCosts::breakdown at the agreed price.
            $table->json('costs')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_listing_id')->constrained()->cascadeOnDelete();
            $table->string('buyer');
            $table->unsignedBigInteger('amount_cents');
            // The most this buyer would pay; never shown to the player.
            $table->unsignedBigInteger('limit_cents');
            $table->date('made_on');
            $table->date('expires_on');
            // open, countered, accepted, rejected, lapsed, walked
            $table->string('status')->default('open');
            $table->unsignedBigInteger('counter_cents')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_offers');
        Schema::dropIfExists('sale_listings');
    }
};
