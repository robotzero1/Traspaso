<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neighbourhoods', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('population');
            $table->decimal('student_index', 4, 2);
            $table->decimal('tourist_index', 4, 2);
            $table->decimal('office_index', 4, 2);
            $table->decimal('transport_index', 4, 2);
            $table->decimal('competition_density', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('neighbourhoods');
    }
};
