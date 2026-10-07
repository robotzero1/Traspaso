<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The real-time clock (SPEC §11): the first day traded, the last
        // day simulated, and decisions waiting to take effect.
        Schema::table('games', function (Blueprint $table) {
            $table->date('started_on')->nullable()->after('start_date');
            $table->date('last_simulated_on')->nullable()->after('started_on');
            $table->json('scheduled_decisions')->nullable()->after('decisions');
        });

        // Games bought before the clock existed carry on from the month
        // they were about to play.
        foreach (DB::table('games')->whereNotNull('business_id')->get(['id', 'start_date', 'current_month']) as $game) {
            $start = Carbon::parse($game->start_date)->startOfMonth();

            DB::table('games')->where('id', $game->id)->update([
                'started_on' => $start->toDateString(),
                'last_simulated_on' => $start->copy()->addMonths($game->current_month - 1)->subDay()->toDateString(),
            ]);
        }

        // What the month end needs to draw up the P&L from stored days.
        Schema::table('day_results', function (Blueprint $table) {
            $table->bigInteger('event_revenue_cents')->default(0)->after('revenue_cents');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['started_on', 'last_simulated_on', 'scheduled_decisions']);
        });

        Schema::table('day_results', function (Blueprint $table) {
            $table->dropColumn('event_revenue_cents');
        });
    }
};
