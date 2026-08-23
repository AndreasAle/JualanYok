<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exact-amount matching for QRIS checkout.
 *
 * The wallet gives us no callback, so the rupiah figure is the only thing tying
 * an incoming transfer to an order. Two open payments sharing an amount would
 * make a transfer genuinely ambiguous, so uniqueness is enforced by the schema
 * rather than hoped for — same approach as plan_payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedSmallInteger('unique_suffix')->nullable()->after('amount');

            /*
             * Holds the payable amount while the payment is still open, and NULL
             * once it settles or lapses. A unique index over a nullable column
             * treats NULLs as distinct on both MySQL and SQLite, giving "at most
             * one open claim per amount" on either engine.
             */
            $table->unsignedBigInteger('claimable_amount')->nullable()->unique()->after('unique_suffix');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['claimable_amount']);
            $table->dropColumn(['unique_suffix', 'claimable_amount']);
        });
    }
};
