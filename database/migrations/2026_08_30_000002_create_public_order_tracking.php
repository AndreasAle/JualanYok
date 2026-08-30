<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'tracking_code')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('tracking_code', 32)->nullable()->unique()->after('access_token');
            });
        }

        DB::table('orders')->whereNull('tracking_code')->orderBy('id')->chunkById(200, function ($orders) {
            foreach ($orders as $order) {
                do {
                    $code = 'JYT-'.Str::upper(Str::random(16));
                } while (DB::table('orders')->where('tracking_code', $code)->exists());

                DB::table('orders')->where('id', $order->id)->update(['tracking_code' => $code]);
            }
        });

        foreach ([
            'driver_name' => 120,
            'driver_phone' => 40,
            'driver_photo_url' => 1000,
            'driver_plate_number' => 40,
        ] as $column => $length) {
            if (! Schema::hasColumn('shipments', $column)) {
                Schema::table('shipments', function (Blueprint $table) use ($column, $length) {
                    $table->string($column, $length)->nullable()->after('tracking_url');
                });
            }
        }

        if (! Schema::hasTable('order_tracking_events')) {
            Schema::create('order_tracking_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('stage', 40);
                $table->string('source', 20)->default('creator');
                $table->string('title', 160);
                $table->text('description')->nullable();
                $table->string('location')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->index(['order_id', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_tracking_events');

        foreach (['driver_name', 'driver_phone', 'driver_photo_url', 'driver_plate_number'] as $column) {
            if (Schema::hasColumn('shipments', $column)) {
                Schema::table('shipments', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        if (Schema::hasColumn('orders', 'tracking_code')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('tracking_code'));
        }
    }
};
