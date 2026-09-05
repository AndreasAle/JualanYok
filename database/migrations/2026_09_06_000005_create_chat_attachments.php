<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files sent inside a conversation.
 *
 * Stored on a private disk and served through a route that checks the caller is
 * one of the two people in the thread. What people send each other in a chat is
 * receipts, screenshots of transfers, sometimes an ID — a public folder with an
 * unguessable name is not privacy, it is a bet that nobody guesses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('kind', 8);            // image | video | file
            // The name the sender saw. Kept for display only; the file on disk
            // is named by us, so nothing the sender typed becomes a path.
            $table->string('name');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->index('chat_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_attachments');
    }
};
