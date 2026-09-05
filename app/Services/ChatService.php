<?php

namespace App\Services;

use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\URL;
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

    /** Attachments per message, and what each kind may weigh. */
    public const MAX_FILES = 5;

    public const LIMITS_KB = ['image' => 8192, 'video' => 25600, 'file' => 15360];

    /**
     * Judged by real type, never by filename.
     *
     * Everything here is either rendered inline or handed back as a download,
     * and the list is deliberately short: a chat is for showing a receipt or a
     * screenshot, not for passing around whatever will run.
     */
    public const ACCEPTED = [
        'image/jpeg' => 'image', 'image/png' => 'image', 'image/webp' => 'image', 'image/gif' => 'image',
        'video/mp4' => 'video', 'video/quicktime' => 'video', 'video/webm' => 'video',
        'application/pdf' => 'file',
        'application/zip' => 'file',
        'application/msword' => 'file',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'file',
        'application/vnd.ms-excel' => 'file',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'file',
        'text/plain' => 'file',
    ];

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
        bool $automatic = false,
        array $files = [],
    ): ChatMessage {
        $body = $this->clean($body);

        $message = $conversation->messages()->create([
            'sender' => $sender,
            'is_auto' => $automatic,
            'user_id' => $author?->id,
            'body' => $body,
            'context' => $context,
        ]);

        foreach (array_slice($files, 0, self::MAX_FILES) as $file) {
            $kind = self::ACCEPTED[$file->getMimeType()] ?? null;

            if ($kind === null) {
                continue;
            }

            ChatAttachment::create([
                'chat_message_id' => $message->id,
                /*
                 * A private disk. What people send each other here is receipts,
                 * transfer screenshots, sometimes an ID — an unguessable name in
                 * a public folder is not privacy, it is a bet that nobody
                 * guesses. Reading one goes through a route that checks the
                 * caller is in the conversation.
                 */
                'path' => $file->store("chat/{$conversation->id}", 'local'),
                'kind' => $kind,
                'name' => Str::limit($file->getClientOriginalName(), 120, ''),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        $fromBuyer = $sender === ChatMessage::BUYER;

        $conversation->forceFill([
            // An attachment with no words still has to read as something in the
            // inbox list, or the thread looks empty from the outside.
            'last_message_preview' => Str::limit($body !== '' ? $body : $this->fileSummary($message), 170),
            'last_message_sender' => $sender,
            'last_message_at' => $message->created_at,
            'seller_unread' => $conversation->seller_unread + ($fromBuyer ? 1 : 0),
            'buyer_unread' => $conversation->buyer_unread + ($fromBuyer ? 0 : 1),
        ])->save();

        if ($fromBuyer) {
            // A message that is only a photo still has to read as something in
            // the alert; an empty line would be a notification about nothing.
            $this->alertSeller($conversation, $body !== '' ? $body : $this->fileSummary($message));
            $this->autoReply($conversation);
        }

        return $message;
    }

    /**
     * The shop's automatic first reply.
     *
     * Sent once, and only before a person has said anything in this thread. A
     * buyer who asks at midnight and hears nothing assumes the shop is
     * abandoned; one sentence about when someone will actually answer keeps
     * them. Repeating it after every question would do the opposite.
     */
    private function autoReply(Conversation $conversation): void
    {
        $store = $conversation->store;

        if (! $store->chat_auto_reply_enabled || blank($store->chat_auto_reply)) {
            return;
        }

        if ($conversation->messages()->where('sender', ChatMessage::SELLER)->exists()) {
            return;
        }

        $this->send($conversation, ChatMessage::SELLER, $store->chat_auto_reply, automatic: true);
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

        if (! $owner || $body === '') {
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

    /** What a wordless message looks like in a list of conversations. */
    private function fileSummary(ChatMessage $message): string
    {
        $kinds = $message->attachments()->pluck('kind');

        if ($kinds->isEmpty()) {
            return '';
        }

        return match ($kinds->first()) {
            'image' => $kinds->count() > 1 ? 'Mengirim '.$kinds->count().' foto' : 'Mengirim foto',
            'video' => 'Mengirim video',
            default => 'Mengirim file',
        };
    }

    /**
     * How long since someone was last at the desk, in words.
     *
     * "Online" only while a chat screen is actually open and polling. Anything
     * older is stated plainly rather than dressed up — a green dot that is not
     * true turns "no reply yet" into "they are ignoring me".
     */
    public function presence(?\Illuminate\Support\Carbon $seenAt): array
    {
        if ($seenAt === null) {
            return ['online' => false, 'label' => 'Biasanya dibalas dalam beberapa jam'];
        }

        $minutes = $seenAt->diffInMinutes(now());

        return match (true) {
            $minutes < 3 => ['online' => true, 'label' => 'Online'],
            $minutes < 60 => ['online' => false, 'label' => 'Aktif '.$minutes.' menit lalu'],
            default => ['online' => false, 'label' => 'Aktif '.$seenAt->diffForHumans()],
        };
    }

    /** Records that this side has a chat screen open right now. */
    public function touchPresence(Conversation $conversation, string $side): void
    {
        if ($side === ChatMessage::SELLER) {
            $conversation->store()->update(['chat_seen_at' => now()]);

            return;
        }

        $conversation->forceFill(['buyer_seen_at' => now()])->save();
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

    /**
     * The product card carried by a message.
     *
     * Attached by a buyer asking about something, and by a seller answering
     * with it — "langsung checkout aja kak" is only useful if the thing to
     * check out is one tap away rather than a name to go and search for.
     */
    public function productContext(Product $product, string $storeUsername): array
    {
        return [
            'kind' => 'product',
            'label' => $product->name,
            'url' => route('storefront.product', [$storeUsername, $product->slug]),
            'image' => $product->thumbnailUrl(),
            'price' => (float) $product->price,
            'buyable' => $product->isBuyable(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function thread(Conversation $conversation, int $limit = 100): array
    {
        return $conversation->messages()
            ->with('attachments')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (ChatMessage $message) => [
                'id' => $message->id,
                'sender' => $message->sender,
                // Marked, not disguised: a buyer has to be able to tell an
                // automatic reply from the person they think they are talking to.
                'is_auto' => (bool) $message->is_auto,
                'body' => $message->body,
                'context' => $message->context,
                'attachments' => $message->attachments->map(fn (ChatAttachment $file) => [
                    'id' => $file->id,
                    'kind' => $file->kind,
                    'name' => $file->name,
                    'size' => $file->size,
                    // A signed, expiring link. The path on disk is never named
                    // to the browser, so there is nothing to walk upwards from.
                    'url' => URL::signedRoute('chat.attachment', ['attachment' => $file->id], now()->addHours(6)),
                ])->all(),
                'read' => $message->read_at !== null,
                'at' => $message->created_at->toIso8601String(),
                'at_human' => $message->created_at->format('H:i'),
            ])
            ->all();
    }

    /**
     * True when a message would carry nothing at all.
     *
     * Checked by the callers so an empty send is refused before a row exists,
     * rather than leaving a blank bubble in someone's thread.
     */
    public function isEmpty(string $body, array $files): bool
    {
        return $this->clean($body) === '' && $files === [];
    }

    private function clean(string $body): string
    {
        // Control characters other than newline serve no purpose in a chat line
        // and are a cheap way to make a message render deceptively.
        $body = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $body) ?? '';

        return Str::limit(trim($body), 2000, '');
    }
}
