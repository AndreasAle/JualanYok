<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A permanent, unguessable key to one order's delivery page.
 *
 * Most buyers never create an account — they type a name and an email and pay.
 * Without this the receipt could only link somewhere login-walled, so a paid
 * digital product had no route to its buyer at all.
 *
 * The token identifies the order, nothing else: it grants no session, no
 * account, and no access to anything beyond this one purchase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('access_token', 64)->nullable()->unique()->after('idempotency_key');
        });

        // Existing orders need one too, or their receipts stay unusable.
        Order::withTrashed()->whereNull('access_token')->chunkById(200, function ($orders) {
            foreach ($orders as $order) {
                $order->forceFill(['access_token' => Str::random(48)])->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['access_token']);
            $table->dropColumn('access_token');
        });
    }
};
