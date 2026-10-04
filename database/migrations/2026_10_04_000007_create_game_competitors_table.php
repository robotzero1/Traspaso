<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The rivals the engine models for a game, as they stand now.
        Schema::create('game_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            // The business it came from, or a generated id for rivals opened by events.
            $table->string('key');
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('distance_metres', 7, 1);
            $table->decimal('price_level', 5, 3);
            $table->decimal('quality', 5, 2);
            $table->decimal('reputation', 5, 2);
            $table->unsignedSmallInteger('seats');
            $table->timestamps();

            $table->unique(['game_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_competitors');
    }
};
