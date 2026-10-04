<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Businesses are generated per game from its seed (single-player MVP).
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            // Position in the generated market. Stable for a seed, unlike
            // the id, so anything seeded per business uses this.
            $table->unsignedSmallInteger('market_index');
            $table->foreignId('neighbourhood_id')->constrained();
            $table->string('fictional_name');
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->string('street_type');
            $table->string('category');
            $table->unsignedSmallInteger('floor_area_m2');
            $table->unsignedSmallInteger('indoor_seats');
            $table->unsignedSmallInteger('terrace_seats');
            $table->bigInteger('rent_month_cents');
            $table->bigInteger('traspaso_cents');
            $table->string('licence');
            $table->string('kitchen');
            $table->unsignedTinyInteger('condition');
            $table->unsignedTinyInteger('equipment_age_years');
            $table->decimal('footfall', 4, 1);
            $table->decimal('base_reputation', 5, 2);
            $table->string('status')->default('for_sale');
            $table->timestamps();

            $table->index(['game_id', 'status']);
            $table->unique(['game_id', 'market_index']);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->foreign('business_id')->references('id')->on('businesses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['business_id']);
        });

        Schema::dropIfExists('businesses');
    }
};
