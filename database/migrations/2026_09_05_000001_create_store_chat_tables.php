<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buyer ↔ seller chat.
 *
 * One thread per shopper per store, whether or not they have an account: a
 * buyer who has to register before asking "is this still available?" simply
 * leaves. Guests are held by a signed, http-only cookie, which is why the token
 * is stored here and never accepted from a request body.
 *
 * Unread counts are kept per side on the conversation rather than derived by
 * counting messages, because both the seller's inbox badge and the buyer's
 * bubble read them on every poll.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_token', 64)->nullable();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();

            $table->string('last_message_preview', 180)->nullable();
            $table->string('last_message_sender', 8)->nullable();  // buyer | seller
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('buyer_unread')->default(0);
            $table->unsignedInteger('seller_unread')->default(0);

            $table->timestamps();

            // A shopper has exactly one thread per store. NULLs are distinct on
            // both MySQL and SQLite, so a signed-in buyer never collides with
            // the guest rows and vice versa.
            $table->unique(['store_id', 'user_id']);
            $table->unique(['store_id', 'guest_token']);
            $table->index(['store_id', 'last_message_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('sender', 8);                          // buyer | seller
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            // What the message is about — a product or an order — so the seller
            // is never answering "is this available?" about nothing.
            $table->json('context')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('conversations');
    }
};
