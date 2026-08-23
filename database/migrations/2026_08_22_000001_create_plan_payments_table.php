<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual QRIS payments for SaaS plans.
 *
 * There is no callback from the wallet provider, so the only thing tying a
 * transfer to a subscriber is the exact rupiah amount. That makes uniqueness a
 * correctness requirement, not a nicety: if two people were both waiting on
 * Rp 49.123, an incoming payment would be genuinely ambiguous and an admin
 * could activate the wrong account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('billing_interval')->default('monthly');

            $table->unsignedInteger('base_amount');       // the plan's list price
            $table->unsignedSmallInteger('unique_suffix'); // the digits that identify this payer
            $table->unsignedInteger('amount');            // base + suffix, what the payer must send

            /*
             * Holds `amount` while the payment can still be claimed, and NULL once
             * it is settled. A unique index over a nullable column treats NULLs as
             * distinct on both MySQL and SQLite, so this enforces "at most one open
             * claim per amount" on either engine — where a partial index would not
             * be portable.
             */
            $table->unsignedInteger('claimable_amount')->nullable()->unique();

            $table->string('status')->default('PENDING');
            $table->text('qris_payload');
            $table->string('payer_note')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();  // payer says they paid
            $table->timestamp('reviewed_at')->nullable();   // admin decided
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_note')->nullable();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_payments');
    }
};
