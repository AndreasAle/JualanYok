<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shop's automatic first reply.
 *
 * A buyer who asks a question at midnight and hears nothing assumes the shop is
 * abandoned. One sentence telling them when a person will actually answer keeps
 * them, and costs the seller nothing.
 *
 * The message is flagged as automatic on the message itself rather than
 * inferred later, because a buyer has to be able to tell a robot from the
 * person they think they are talking to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('chat_auto_reply_enabled')->default(false)->after('whatsapp');
            $table->text('chat_auto_reply')->nullable()->after('chat_auto_reply_enabled');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->boolean('is_auto')->default(false)->after('sender');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('is_auto');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['chat_auto_reply_enabled', 'chat_auto_reply']);
        });
    }
};
