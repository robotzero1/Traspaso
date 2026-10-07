<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How the spot's custom has drifted since purchase (1.0 = as bought).
        Schema::table('game_business_states', function (Blueprint $table) {
            $table->decimal('local_trend', 6, 4)->default(1)->after('stock_quality');
        });
    }

    public function down(): void
    {
        Schema::table('game_business_states', function (Blueprint $table) {
            $table->dropColumn('local_trend');
        });
    }
};
