<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who is actually at the desk.
 *
 * Both timestamps are written by real activity — the seller's inbox polling,
 * the buyer's panel polling — so "online" means someone has the conversation
 * open, not that they signed in last week. A presence dot that lies is worse
 * than no dot: it turns "no reply yet" into "they are ignoring me".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->timestamp('chat_seen_at')->nullable()->after('chat_auto_reply');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('buyer_seen_at')->nullable()->after('buyer_unread');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('buyer_seen_at');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('chat_seen_at');
        });
    }
};
