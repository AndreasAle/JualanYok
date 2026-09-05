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

        // Opening the inbox is what makes the shop "online" to buyers, so it is
        // recorded here rather than guessed from a login timestamp.
        $store->forceFill(['chat_seen_at' => now()])->save();

        return Inertia::render('Creator/Chat', [
            'autoReply' => [
                'enabled' => (bool) $store->chat_auto_reply_enabled,
                'message' => $store->chat_auto_reply,
            ],
            'conversations' => $conversations,
            'active' => $active ? [
                'id' => $active->id,
                'name' => $active->buyerName(),
                'is_guest' => $active->user_id === null,
                'messages' => $this->chats->thread($active),
                'buyer' => $this->chats->presence($active->buyer_seen_at),
            ] : null,

            // For the "kirim produk" picker: what this shop can actually offer
            // in a reply, so a link in chat never points at a dead page.
            'products' => $store->products()
                ->publiclyListed()
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'thumbnail_url' => $product->thumbnailUrl(),
                ]),
        ]);
    }

    /**
     * The one line a shop says while nobody is at the desk.
     *
     * Kept short on purpose — it is read by someone who has just asked a real
     * question, and a paragraph of marketing in that moment reads as being
     * fobbed off.
     */
    public function autoReply(Request $request): \Illuminate\Http\RedirectResponse
    {
        $store = $request->user()->store;

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['enabled'] && blank($data['message'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'message' => 'Tulis pesannya dulu sebelum diaktifkan.',
            ]);
        }

        $store->forceFill([
            'chat_auto_reply_enabled' => $data['enabled'],
            'chat_auto_reply' => $data['message'] ?: null,
        ])->save();

        return back()->with('success', $data['enabled']
            ? 'Balasan otomatis aktif.'
            : 'Balasan otomatis dimatikan.');
    }

    /** Polled by an open thread, so it stays cheap and returns only messages. */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeThread($request, $conversation);
        $this->chats->markRead($conversation, ChatMessage::SELLER);
        $this->chats->touchPresence($conversation, ChatMessage::SELLER);

        return response()->json([
            'messages' => $this->chats->thread($conversation),
            'buyer' => $this->chats->presence($conversation->fresh()->buyer_seen_at),
        ]);
    }

    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeThread($request, $conversation);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'product_id' => ['nullable', 'integer'],
            'files' => ['nullable', 'array', 'max:'.ChatService::MAX_FILES],
            'files.*' => ['file', 'mimetypes:'.implode(',', array_keys(ChatService::ACCEPTED))],
        ], [
            'files.*.mimetypes' => 'Kirim foto, video, atau dokumen (PDF, Word, Excel, ZIP, teks).',
        ]);

        $files = $request->file('files', []);

        // A product the shop does not sell cannot be recommended by it.
        $context = null;

        if (! empty($data['product_id'])) {
            $product = $request->user()->store->products()->find($data['product_id']);
            $context = $product
                ? $this->chats->productContext($product, $request->user()->store->username)
                : null;
        }

        if ($this->chats->isEmpty((string) ($data['body'] ?? ''), $files) && $context === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'body' => 'Tulis balasan, lampirkan file, atau pilih produk dulu.',
            ]);
        }

        $this->chats->send(
            $conversation,
            ChatMessage::SELLER,
            (string) ($data['body'] ?? ''),
            $context,
            $request->user(),
            files: $files,
        );

        return response()->json(['messages' => $this->chats->thread($conversation)]);
    }

    private function authorizeThread(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->store_id === $request->user()->store?->id, 404);
    }
}
