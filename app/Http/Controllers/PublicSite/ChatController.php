<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Product;
use App\Models\Store;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        // Presence is read from the shop whether or not a thread exists yet:
        // "are they around?" is asked before the first message, not after.
        $presence = $this->chats->presence($store->chat_seen_at);

        if (! $conversation) {
            return response()->json(['messages' => [], 'unread' => 0, 'seller' => $presence]);
        }

        $this->chats->markRead($conversation, ChatMessage::BUYER);
        $this->chats->touchPresence($conversation, ChatMessage::BUYER);

        return response()->json([
            'messages' => $this->chats->thread($conversation),
            'unread' => 0,
            'seller' => $presence,
        ]);
    }

    public function store(Request $request, Store $store): JsonResponse
    {
        abort_unless($store->isLive(), 404);

        // A seller messaging their own shop would open a thread with themselves
        // and put an unread badge on their own sidebar.
        abort_if($store->user_id === $request->user()?->id, 422, 'Ini toko kamu sendiri.');

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'name' => ['nullable', 'string', 'max:80'],
            'product_id' => ['nullable', 'integer'],
            'files' => ['nullable', 'array', 'max:'.ChatService::MAX_FILES],
            'files.*' => ['file', 'mimetypes:'.implode(',', array_keys(ChatService::ACCEPTED))],
        ], [
            'files.*.mimetypes' => 'Kirim foto, video, atau dokumen (PDF, Word, Excel, ZIP, teks).',
            'files.max' => 'Maksimal '.ChatService::MAX_FILES.' file sekali kirim.',
        ]);

        $files = $request->file('files', []);

        $this->assertFileSizes($files);

        if ($this->chats->isEmpty((string) ($data['body'] ?? ''), $files)) {
            throw ValidationException::withMessages(['body' => 'Tulis pesan atau lampirkan file dulu.']);
        }

        $conversation = $this->chats->openForBuyer($request, $store, $data['name'] ?? null);

        // A product reference is only accepted for a product this store
        // actually sells, so the seller cannot be shown someone else's item.
        $context = null;

        if (! empty($data['product_id'])) {
            $product = Product::where('store_id', $store->id)->find($data['product_id']);
            $context = $product ? $this->chats->productContext($product, $store->username) : null;
        }

        $this->chats->send(
            $conversation,
            ChatMessage::BUYER,
            (string) ($data['body'] ?? ''),
            $context,
            $request->user(),
            files: $files,
        );

        return response()->json([
            'messages' => $this->chats->thread($conversation),
            'seller' => $this->chats->presence($store->chat_seen_at),
        ]);
    }

    /**
     * Each kind against its own ceiling.
     *
     * One limit for everything would either refuse an ordinary phone video or
     * let a 25 MB "document" through, and the two are not the same risk.
     *
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     */
    private function assertFileSizes(array $files): void
    {
        foreach ($files as $index => $file) {
            $kind = ChatService::ACCEPTED[$file->getMimeType()] ?? 'file';
            $limitKb = ChatService::LIMITS_KB[$kind];

            if ($file->getSize() > $limitKb * 1024) {
                throw ValidationException::withMessages([
                    "files.{$index}" => match ($kind) {
                        'video' => 'Video maksimal '.round($limitKb / 1024).' MB.',
                        'image' => 'Foto maksimal '.round($limitKb / 1024).' MB.',
                        default => 'File maksimal '.round($limitKb / 1024).' MB.',
                    },
                ]);
            }
        }
    }
}
