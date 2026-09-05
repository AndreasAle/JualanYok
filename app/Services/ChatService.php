<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Buyer ↔ seller messaging.
 *
 * The whole point is that a question can be asked before the sale, by someone
 * who has not signed up. So a guest gets a thread held by an http-only cookie
 * scoped to the one store — the browser can present the token, but it can never
 * read it, name a different one, or reach another shop's threads with it.
 */
class ChatService
{
    /** Long enough that guessing is not a strategy. */
    private const TOKEN_BYTES = 32;

    private const COOKIE_DAYS = 180;

    public function __construct(private readonly NotificationCenterService $notifications) {}

    private function cookieName(Store $store): string
    {
        return "jy_chat_{$store->id}";
    }

    /**
     * The thread for whoever is browsing, without creating one.
     *
     * Read paths use this: opening the chat panel should not leave an empty
     * conversation in the seller's inbox for every visitor who was curious
     * about the button.
     */
    public function findForBuyer(Request $request, Store $store): ?Conversation
    {
        if ($user = $request->user()) {
            return Conversation::where('store_id', $store->id)->where('user_id', $user->id)->first();
        }

        $token = (string) $request->cookie($this->cookieName($store));

        return $token === ''
            ? null
            : Conversation::where('store_id', $store->id)->where('guest_token', $token)->first();
    }

    /**
     * The thread for whoever is browsing, creating it on first message.
     *
     * A guest who later signs in keeps their history: the existing row is
     * claimed rather than abandoned, so the seller does not suddenly see two
     * threads from the same person.
     */
    public function openForBuyer(Request $request, Store $store, ?string $guestName = null): Conversation
    {
        $user = $request->user();
        $token = (string) $request->cookie($this->cookieName($store));

        $existing = $token !== ''
            ? Conversation::where('store_id', $store->id)->where('guest_token', $token)->first()
            : null;

        if ($user) {
            $owned = Conversation::where('store_id', $store->id)->where('user_id', $user->id)->first();

            if ($owned) {
                return $owned;
            }

            if ($existing) {
                $existing->forceFill([
                    'user_id' => $user->id,
                    'guest_token' => null,
                    'guest_name' => null,
                    'guest_email' => null,
                ])->save();

                return $existing;
            }

            return Conversation::create(['store_id' => $store->id, 'user_id' => $user->id]);
        }

        if ($existing) {
            if ($guestName && blank($existing->guest_name)) {
                $existing->forceFill(['guest_name' => $guestName])->save();
            }

            return $existing;
        }

        $fresh = Conversation::create([
            'store_id' => $store->id,
            'guest_token' => bin2hex(random_bytes(self::TOKEN_BYTES)),
            'guest_name' => $guestName,
        ]);

        // Queued rather than set directly so it rides out on this response.
        Cookie::queue(Cookie::make(
            $this->cookieName($store),
            $fresh->guest_token,
            self::COOKIE_DAYS * 24 * 60,
            null, null, null,
            httpOnly: true,
        ));

        return $fresh;
    }

    /**
     * Records a message and moves the unread counter on the other side.
     *
     * The body is stored as the plain text it was typed as. It is never
     * interpolated into markup anywhere — the clients render it as a text node
     * — so a seller cannot be attacked through a buyer's question.
     */
    public function send(
        Conversation $conversation,
        string $sender,
        string $body,
        ?array $context = null,
        ?User $author = null,
    ): ChatMessage {
        $body = $this->clean($body);

        $message = $conversation->messages()->create([
            'sender' => $sender,
            'user_id' => $author?->id,
            'body' => $body,
            'context' => $context,
        ]);

        $fromBuyer = $sender === ChatMessage::BUYER;

        $conversation->forceFill([
            'last_message_preview' => Str::limit($body, 170),
            'last_message_sender' => $sender,
            'last_message_at' => $message->created_at,
            'seller_unread' => $conversation->seller_unread + ($fromBuyer ? 1 : 0),
            'buyer_unread' => $conversation->buyer_unread + ($fromBuyer ? 0 : 1),
        ])->save();

        if ($fromBuyer) {
            $this->alertSeller($conversation, $body);
        }

        return $message;
    }

    /**
     * Tells the seller once per hour per thread, not once per message.
     *
     * Someone typing four short lines is one conversation, and four
     * notifications for it would train the seller to ignore the fifth.
     */
    private function alertSeller(Conversation $conversation, string $body): void
    {
        $owner = $conversation->store->owner;

        if (! $owner) {
            return;
        }

        $this->notifications->sendOnce($owner, [
            'type' => 'chat.message',
            'category' => 'chat',
            'priority' => 'normal',
            'title' => 'Pesan dari '.$conversation->buyerName(),
            'message' => Str::limit($body, 120),
            'url' => '/dashboard/chat?percakapan='.$conversation->id,
            'action_label' => 'Balas',
            'group_key' => 'chat:'.$conversation->id,
        ], hours: 1);
    }

    public function markRead(Conversation $conversation, string $side): void
    {
        $conversation->forceFill([
            $side === ChatMessage::BUYER ? 'buyer_unread' : 'seller_unread' => 0,
        ])->save();

        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender', '!=', $side)
            ->update(['read_at' => now()]);
    }

    /** The reference line attached to a message opened from a product page. */
    public function productContext(Product $product, string $storeUsername): array
    {
        return [
            'kind' => 'product',
            'label' => $product->name,
            'url' => route('storefront.product', [$storeUsername, $product->slug]),
            'image' => $product->thumbnailUrl(),
            'price' => (float) $product->price,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function thread(Conversation $conversation, int $limit = 100): array
    {
        return $conversation->messages()
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (ChatMessage $message) => [
                'id' => $message->id,
                'sender' => $message->sender,
                'body' => $message->body,
                'context' => $message->context,
                'read' => $message->read_at !== null,
                'at' => $message->created_at->toIso8601String(),
                'at_human' => $message->created_at->format('H:i'),
            ])
            ->all();
    }

    private function clean(string $body): string
    {
        // Control characters other than newline serve no purpose in a chat line
        // and are a cheap way to make a message render deceptively.
        $body = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $body) ?? '';

        return Str::limit(trim($body), 2000, '');
    }
}
