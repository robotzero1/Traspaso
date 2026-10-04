<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SPEC §5 footfall_points: the precomputed surface, OSM-derived (ODbL).
        // Only commercial points are stored; businesses are placed on them.
        Schema::create('footfall_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('neighbourhood_id')->constrained()->cascadeOnDelete();
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->unsignedBigInteger('osm_way_id')->nullable();
            $table->string('street_type');
            $table->decimal('poi_score', 5, 4);
            $table->decimal('centrality_score', 5, 4);
            $table->decimal('catchment_score', 5, 4);
            $table->decimal('transport_score', 5, 4);
            $table->decimal('footfall', 4, 1);
            $table->decimal('footfall_morning', 4, 1);
            $table->decimal('footfall_lunch', 4, 1);
            $table->decimal('footfall_afternoon', 4, 1);
            $table->decimal('footfall_evening', 4, 1);
            $table->decimal('footfall_night', 4, 1);
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->foreignId('footfall_point_id')->nullable()->after('lng')->constrained()->nullOnDelete();
            // Footfall per day part from the surface; null before real data.
            $table->json('footfall_by_day_part')->nullable()->after('footfall');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('footfall_point_id');
            $table->dropColumn('footfall_by_day_part');
        });

        Schema::dropIfExists('footfall_points');
    }
};
