<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('neighbourhoods', function (Blueprint $table) {
            // Until real boundaries arrive (milestone 8), a centre and radius.
            $table->decimal('centre_lat', 9, 6)->nullable();
            $table->decimal('centre_lng', 9, 6)->nullable();
            $table->unsignedInteger('radius_m')->nullable();
            // GeoJSON geometry from the committed extracts, when there is one.
            $table->json('boundary')->nullable();
        });

        // SPEC §5 points_of_interest. OSM-derived rows keep their osm_id.
        Schema::create('points_of_interest', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->string('osm_id')->nullable();
            $table->timestamps();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_of_interest');

        Schema::table('neighbourhoods', function (Blueprint $table) {
            $table->dropColumn(['centre_lat', 'centre_lng', 'radius_m', 'boundary']);
        });
    }
};
