<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Manual pedestrian counts for geo:calibrate (SPEC §8): only where,
        // when, for how long and how many. No photos, no people.
        Schema::create('pedestrian_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->string('day_part');
            $table->date('counted_on');
            $table->unsignedSmallInteger('minutes');
            $table->unsignedInteger('count');
            $table->string('note', 80)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedestrian_counts');
    }
};
