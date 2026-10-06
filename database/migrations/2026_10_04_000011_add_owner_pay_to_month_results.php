<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the owner took out to live on that month (not a business cost).
        Schema::table('month_results', function (Blueprint $table) {
            $table->integer('owner_pay_cents')->default(0)->after('profit_cents');
        });
    }

    public function down(): void
    {
        Schema::table('month_results', function (Blueprint $table) {
            $table->dropColumn('owner_pay_cents');
        });
    }
};
