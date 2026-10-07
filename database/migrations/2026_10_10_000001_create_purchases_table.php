<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What has been bought (SPEC §11): capital tiers belong to an
        // account; a viability report can be bought without one.
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('viability_report_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product');
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('status')->default('pending');
            $table->string('stripe_session_id')->nullable()->unique();
            // The buyer asked for immediate delivery and accepted losing the
            // 14-day right of withdrawal for digital content (RDL 1/2007, art. 103 m).
            $table->timestamp('withdrawal_waived_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
