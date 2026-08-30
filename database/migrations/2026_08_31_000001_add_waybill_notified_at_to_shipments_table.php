<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shipments', 'waybill_notified_at')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->timestamp('waybill_notified_at')->nullable()->after('last_synced_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shipments', 'waybill_notified_at')) {
            Schema::table('shipments', fn (Blueprint $table) => $table->dropColumn('waybill_notified_at'));
        }
    }
};
