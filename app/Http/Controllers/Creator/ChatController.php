<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The seller's inbox.
 *
 * Every lookup is scoped to the signed-in creator's own store, so a
 * conversation id from the address bar can only ever resolve to one of their
 * own threads.
 */
class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chats) {}

    public function index(Request $request): Response
    {
        $store = $request->user()->store;

        $conversations = Conversation::where('store_id', $store->id)
            ->whereNotNull('last_message_at')
            ->with('buyer:id,name,username,avatar_path')
            ->orderByDesc('last_message_at')
            ->limit(60)
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'name' => $c->buyerName(),
                'avatar_url' => $c->buyer?->avatarUrl(),
                'is_guest' => $c->user_id === null,
                'preview' => $c->last_message_preview,
                'from_buyer' => $c->last_message_sender === ChatMessage::BUYER,
                'unread' => (int) $c->seller_unread,
                'at_human' => $c->last_message_at?->diffForHumans(short: true),
            ]);

        $active = $request->filled('percakapan')
            ? Conversation::where('store_id', $store->id)->find($request->query('percakapan'))
            : null;

        if ($active) {
            $this->chats->markRead($active, ChatMessage::SELLER);
        }

        return Inertia::render('Creator/Chat', [
            'conversations' => $conversations,
            'active' => $active ? [
                'id' => $active->id,
                'name' => $active->buyerName(),
                'is_guest' => $active->user_id === null,
                'messages' => $this->chats->thread($active),
            ] : null,
        ]);
    }

    /** Polled by an open thread, so it stays cheap and returns only messages. */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeThread($request, $conversation);
        $this->chats->markRead($conversation, ChatMessage::SELLER);

        return response()->json(['messages' => $this->chats->thread($conversation)]);
    }

    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeThread($request, $conversation);

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $this->chats->send($conversation, ChatMessage::SELLER, $data['body'], null, $request->user());

        return response()->json(['messages' => $this->chats->thread($conversation)]);
    }

    private function authorizeThread(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->store_id === $request->user()->store?->id, 404);
    }
}
