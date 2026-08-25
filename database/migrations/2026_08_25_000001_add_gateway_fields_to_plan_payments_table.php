<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_payments', function (Blueprint $table) {
            $table->string('provider')->default('qris')->after('billing_interval');
            $table->string('method')->default('qris')->after('provider');
            $table->string('channel')->nullable()->after('method');
            $table->string('gateway_transaction_id')->nullable()->after('channel');
            $table->decimal('gateway_fee', 14, 2)->default(0)->after('gateway_transaction_id');
            $table->json('instructions')->nullable()->after('qris_payload');
            $table->text('redirect_url')->nullable()->after('instructions');
            $table->json('gateway_response')->nullable()->after('redirect_url');
            $table->text('gateway_error')->nullable()->after('gateway_response');
            $table->timestamp('paid_at')->nullable()->after('confirmed_at');

            $table->unique(['provider', 'gateway_transaction_id'], 'plan_payments_gateway_transaction_unique');
        });
    }

    public function down(): void
    {
        Schema::table('plan_payments', function (Blueprint $table) {
            $table->dropUnique('plan_payments_gateway_transaction_unique');
            $table->dropColumn([
                'provider',
                'method',
                'channel',
                'gateway_transaction_id',
                'gateway_fee',
                'instructions',
                'redirect_url',
                'gateway_response',
                'gateway_error',
                'paid_at',
            ]);
        });
    }
};
