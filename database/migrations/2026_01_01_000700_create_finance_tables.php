<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3)->default('IDR');

            // Cached projections of the ledger. Never written to directly —
            // only LedgerService mutates these, inside the same transaction
            // that writes the ledger entry.
            $table->decimal('pending_balance', 16, 2)->default(0);
            $table->decimal('available_balance', 16, 2)->default(0);
            $table->decimal('held_balance', 16, 2)->default(0);
            $table->decimal('withdrawn_balance', 16, 2)->default(0);
            $table->decimal('lifetime_earned', 16, 2)->default(0);
            $table->boolean('is_frozen')->default(false);
            $table->text('freeze_reason')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'currency']);
        });

        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();     // platform_revenue, payment_fees, payable_sellers
            $table->string('name');
            $table->string('type');               // asset | liability | revenue | expense
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Append-only. Rows are never updated or deleted; corrections are new
        // entries with the opposite sign.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('bucket')->default('PENDING');
            $table->decimal('amount', 16, 2);      // signed: credit positive, debit negative
            $table->decimal('balance_after', 16, 2)->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->nullableMorphs('reference');
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['wallet_id', 'bucket']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('payout_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('bank'); // bank | ewallet
            $table->string('provider');              // BCA, Mandiri, GoPay, OVO...
            $table->string('account_name');
            $table->text('account_number');          // encrypted at rest
            $table->string('account_number_last4', 8)->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status')->default('unverified'); // unverified | verified | rejected
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payout_method_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 16, 2);
            $table->decimal('fee', 16, 2)->default(0);
            $table->decimal('net_amount', 16, 2);
            $table->string('currency', 3)->default('IDR');
            $table->string('status')->default('REQUESTED');
            $table->json('payout_snapshot')->nullable(); // masked account details at request time
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->string('transfer_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('payout_methods');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('wallets');
    }
};
