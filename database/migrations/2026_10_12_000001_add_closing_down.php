<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Closing down (SPEC §12): when, and what it cost (Closure::breakdown).
        Schema::table('games', function (Blueprint $table) {
            $table->date('closes_on')->nullable();
            $table->json('closure')->nullable();
        });

        // A quick sale to a buyer of last resort.
        Schema::table('sale_listings', function (Blueprint $table) {
            $table->boolean('quick')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('games', fn (Blueprint $table) => $table->dropColumn(['closes_on', 'closure']));
        Schema::table('sale_listings', fn (Blueprint $table) => $table->dropColumn('quick'));
    }
};
