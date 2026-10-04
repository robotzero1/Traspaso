<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rivals that aren't listings (real cafés and bars from OSM, under
        // fictional names) keep their own position.
        Schema::table('game_competitors', function (Blueprint $table) {
            $table->decimal('lat', 9, 6)->nullable()->after('seats');
            $table->decimal('lng', 9, 6)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('game_competitors', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng']);
        });
    }
};
