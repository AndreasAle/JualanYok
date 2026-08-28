<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_methods', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');
            $table->index(['status', 'created_at'], 'payout_methods_review_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payout_methods', function (Blueprint $table) {
            $table->dropIndex('payout_methods_review_queue_idx');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviewed_at', 'review_note']);
        });
    }
};
