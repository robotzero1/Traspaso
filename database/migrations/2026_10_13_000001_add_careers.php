<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A career (SPEC §12): each café is a game; the next one carries on
        // from the last with its cash. The market changes week by week.
        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('previous_game_id')->nullable()->unique()->constrained('games')->nullOnDelete();
            $table->date('market_refreshed_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_game_id');
            $table->dropColumn('market_refreshed_on');
        });
    }
};
