<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Product;
use App\Models\Store;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The buyer's side of store chat.
 *
 * Nothing here takes a conversation id. Which thread the caller may touch is
 * derived from their session or their http-only cookie, so there is no
 * identifier to tamper with and no way to read someone else's questions.
 */
class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chats) {}

    public function show(Request $request, Store $store): JsonResponse
    {
        abort_unless($store->isLive(), 404);

        $conversation = $this->chats->findForBuyer($request, $store);

        if (! $conversation) {
            return response()->json(['messages' => [], 'unread' => 0]);
        }

        $this->chats->markRead($conversation, ChatMessage::BUYER);

        return response()->json([
            'messages' => $this->chats->thread($conversation),
            'unread' => 0,
        ]);
    }

    public function store(Request $request, Store $store): JsonResponse
    {
        abort_unless($store->isLive(), 404);

        // A seller messaging their own shop would open a thread with themselves
        // and put an unread badge on their own sidebar.
        abort_if($store->user_id === $request->user()?->id, 422, 'Ini toko kamu sendiri.');

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'name' => ['nullable', 'string', 'max:80'],
            'product_id' => ['nullable', 'integer'],
        ]);

        $conversation = $this->chats->openForBuyer($request, $store, $data['name'] ?? null);

        // A product reference is only accepted for a product this store
        // actually sells, so the seller cannot be shown someone else's item.
        $context = null;

        if (! empty($data['product_id'])) {
            $product = Product::where('store_id', $store->id)->find($data['product_id']);
            $context = $product ? $this->chats->productContext($product, $store->username) : null;
        }

        $this->chats->send($conversation, ChatMessage::BUYER, $data['body'], $context, $request->user());

        return response()->json(['messages' => $this->chats->thread($conversation)]);
    }
}
