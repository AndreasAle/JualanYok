<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('reserve_balance', 16, 2)->default(0)->after('held_balance');
            // Positive means money the user owes; display it as a negative balance.
            $table->decimal('negative_balance', 16, 2)->default(0)->after('reserve_balance');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('commission_base', 14, 2)->default(0)->after('tax_total');
            $table->decimal('platform_fee_rate', 8, 4)->default(0)->after('commission_base');
            $table->decimal('gateway_fee_estimated', 14, 2)->default(0)->after('payment_fee');
            $table->decimal('gateway_fee_actual', 14, 2)->default(0)->after('gateway_fee_estimated');
            $table->string('gateway_fee_bearer', 20)->default('SELLER')->after('gateway_fee_actual');
            $table->decimal('reserve_amount', 14, 2)->default(0)->after('seller_net');
            $table->decimal('reserve_rate', 8, 4)->default(0)->after('reserve_amount');
            $table->decimal('debt_offset', 14, 2)->default(0)->after('reserve_rate');
            $table->timestamp('reserve_release_at')->nullable()->after('funds_release_at');
            $table->decimal('shipping_cost_actual', 14, 2)->default(0)->after('shipping_total');
            $table->decimal('shipping_variance', 14, 2)->default(0)->after('shipping_cost_actual');
            $table->decimal('split_fee_actual', 14, 2)->default(0)->after('gateway_fee_bearer');
            $table->decimal('contribution_margin', 14, 2)->default(0)->after('split_fee_actual');
            // Existing rows are legacy until they are explicitly backfilled.
            // New payments are promoted to v2 only after the new settlement
            // transaction and platform journal both succeed.
            $table->unsignedSmallInteger('settlement_version')->default(1)->after('contribution_margin');

            $table->index(['reserve_release_at', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('fee_estimated', 14, 2)->default(0)->after('fee');
            $table->string('fee_source', 20)->default('ESTIMATE')->after('fee_estimated');
            $table->unsignedSmallInteger('settlement_days')->default(0)->after('fee_source');
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->decimal('reversed_amount', 14, 2)->default(0)->after('amount');
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->decimal('seller_clawback', 14, 2)->default(0)->after('amount');
            $table->decimal('reserve_clawback', 14, 2)->default(0)->after('seller_clawback');
            $table->decimal('seller_debt_created', 14, 2)->default(0)->after('reserve_clawback');
            $table->decimal('affiliate_clawback', 14, 2)->default(0)->after('seller_debt_created');
            $table->decimal('affiliate_debt_created', 14, 2)->default(0)->after('affiliate_clawback');
            $table->decimal('platform_fee_reversal', 14, 2)->default(0)->after('affiliate_debt_created');
            $table->decimal('shipping_reversal', 14, 2)->default(0)->after('platform_fee_reversal');
            $table->decimal('tax_reversal', 14, 2)->default(0)->after('shipping_reversal');
            $table->decimal('platform_loss', 14, 2)->default(0)->after('tax_reversal');
            $table->string('execution_mode', 20)->nullable()->after('status');
            $table->string('order_status_before', 40)->nullable()->after('execution_mode');
            $table->foreignId('approved_by')->nullable()->after('processed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('processed_at');
            $table->string('transfer_reference')->nullable()->after('admin_note');
            $table->string('provider_reference')->nullable()->after('transfer_reference');
            $table->json('provider_response')->nullable()->after('provider_reference');

            $table->index(['status', 'approved_at']);
        });

        Schema::create('payment_cost_rules', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('method', 40);
            $table->string('channel', 40)->default('');
            $table->decimal('fee_percent', 8, 4)->default(0);
            $table->decimal('fee_fixed', 14, 2)->default(0);
            $table->unsignedSmallInteger('settlement_days')->default(0);
            $table->decimal('minimum_amount', 14, 2)->nullable();
            $table->decimal('maximum_amount', 14, 2)->nullable();
            $table->string('fee_bearer', 20)->default('SELLER');
            $table->string('source', 30)->default('CONFIG');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['provider', 'method', 'channel']);
            $table->index(['is_active', 'effective_from', 'effective_until']);
        });

        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('type', 20); // ASSET | LIABILITY | REVENUE | EXPENSE
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('provider_api_usages', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('operation', 40);
            $table->string('request_hash', 64)->nullable();
            $table->decimal('cost', 14, 2)->default(0);
            $table->string('status', 20)->default('SUCCESS');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['provider', 'operation', 'occurred_at']);
            $table->index(['status', 'occurred_at']);
        });

        Schema::create('financial_journals', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 60);
            $table->nullableMorphs('reference');
            $table->string('idempotency_key')->unique();
            $table->string('currency', 3)->default('IDR');
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->index(['event_type', 'posted_at']);
        });

        Schema::create('financial_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->string('direction', 6); // DEBIT | CREDIT
            $table->decimal('amount', 16, 2);
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['financial_account_id', 'created_at']);
            $table->index(['store_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_postings');
        Schema::dropIfExists('financial_journals');
        Schema::dropIfExists('provider_api_usages');
        Schema::dropIfExists('financial_accounts');
        Schema::dropIfExists('payment_cost_rules');

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropIndex(['status', 'approved_at']);
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'seller_clawback', 'reserve_clawback', 'seller_debt_created',
                'affiliate_clawback', 'affiliate_debt_created', 'platform_fee_reversal',
                'shipping_reversal', 'tax_reversal', 'platform_loss', 'execution_mode',
                'order_status_before', 'approved_at', 'transfer_reference',
                'provider_reference', 'provider_response',
            ]);
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->dropColumn('reversed_amount');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['fee_estimated', 'fee_source', 'settlement_days']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['reserve_release_at', 'status']);
            $table->dropColumn([
                'commission_base', 'platform_fee_rate', 'gateway_fee_estimated',
                'gateway_fee_actual', 'gateway_fee_bearer', 'reserve_amount',
                'reserve_rate', 'debt_offset', 'reserve_release_at', 'shipping_cost_actual',
                'shipping_variance', 'split_fee_actual', 'contribution_margin',
                'settlement_version',
            ]);
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn(['reserve_balance', 'negative_balance']);
        });
    }
};
