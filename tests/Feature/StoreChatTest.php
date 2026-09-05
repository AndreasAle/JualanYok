<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Store;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Buyer ↔ seller chat.
 *
 * The interesting part is not that messages arrive — it is that a thread is
 * reachable by exactly one shopper. No conversation id is accepted from the
 * buyer's side at all: which thread they touch comes from their session or an
 * http-only cookie, so there is nothing to tamper with.
 */
class StoreChatTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        $this->store = $this->makeStore();
        $this->store->forceFill(['is_published' => true])->save();
    }

    public function test_a_guest_can_ask_a_question_without_signing_up(): void
    {
        $response = $this->postJson("/{$this->store->username}/chat", [
            'body' => 'Halo, stoknya masih ada?',
            'name' => 'Luna',
        ])->assertOk();

        $this->assertSame('Halo, stoknya masih ada?', $response->json('messages.0.body'));

        $conversation = Conversation::firstOrFail();
        $this->assertNull($conversation->user_id);
        $this->assertSame('Luna', $conversation->guest_name);
        $this->assertSame(1, $conversation->seller_unread);
    }

    public function test_the_guest_token_is_never_readable_by_the_page(): void
    {
        $response = $this->postJson("/{$this->store->username}/chat", ['body' => 'Halo']);

        $cookie = collect($response->headers->getCookies())
            ->firstWhere(fn ($c) => str_starts_with($c->getName(), 'jy_chat_'));

        $this->assertNotNull($cookie, 'Tamu harus dapat cookie percakapan.');
        $this->assertTrue($cookie->isHttpOnly(), 'Token percakapan tidak boleh terbaca JavaScript.');
    }

    public function test_a_second_guest_cannot_read_the_first_guests_thread(): void
    {
        $this->postJson("/{$this->store->username}/chat", ['body' => 'Rahasia saya'])->assertOk();

        // A different browser: no cookie, so no thread.
        $this->flushSession();

        $this->getJson("/{$this->store->username}/chat")
            ->assertOk()
            ->assertJsonPath('messages', []);
    }

    public function test_a_product_reference_must_belong_to_this_store(): void
    {
        $other = $this->makeStore(null, ['username' => 'tokolain']);
        $foreign = $this->makeProduct($other);

        $this->postJson("/{$this->store->username}/chat", [
            'body' => 'Ini ada?',
            'product_id' => $foreign->id,
        ])->assertOk();

        // Silently dropped rather than shown to a seller who does not sell it.
        $this->assertNull(ChatMessage::firstOrFail()->context);
    }

    public function test_a_product_from_this_store_rides_along_with_the_question(): void
    {
        $product = $this->makeProduct($this->store);

        $this->postJson("/{$this->store->username}/chat", [
            'body' => 'Warna lain ada?',
            'product_id' => $product->id,
        ])->assertOk();

        $this->assertSame($product->name, ChatMessage::firstOrFail()->context['label']);
    }

    public function test_the_seller_sees_and_answers_from_their_own_inbox(): void
    {
        $this->postJson("/{$this->store->username}/chat", ['body' => 'Bisa COD?'])->assertOk();

        $conversation = Conversation::firstOrFail();

        $this->actingAs($this->store->owner)
            ->get('/dashboard/chat?percakapan='.$conversation->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('conversations', 1)->where('active.id', $conversation->id));

        // Opening the thread clears the seller's badge.
        $this->assertSame(0, $conversation->fresh()->seller_unread);

        $this->actingAs($this->store->owner)
            ->postJson('/dashboard/chat/'.$conversation->id, ['body' => 'Bisa kak.'])
            ->assertOk();

        $this->assertSame(1, $conversation->fresh()->buyer_unread);
    }

    public function test_one_seller_cannot_open_another_stores_conversation(): void
    {
        $this->postJson("/{$this->store->username}/chat", ['body' => 'Halo'])->assertOk();

        $conversation = Conversation::firstOrFail();
        $outsider = $this->makeStore(null, ['username' => 'tokolain'])->owner;

        $this->actingAs($outsider)
            ->getJson('/dashboard/chat/'.$conversation->id.'/pesan')
            ->assertNotFound();

        $this->actingAs($outsider)
            ->postJson('/dashboard/chat/'.$conversation->id, ['body' => 'Menyusup'])
            ->assertNotFound();

        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_an_empty_message_is_refused(): void
    {
        $this->postJson("/{$this->store->username}/chat", ['body' => '   '])
            ->assertJsonValidationErrors('body');

        $this->assertSame(0, Conversation::count());
    }

    public function test_opening_the_panel_does_not_create_an_empty_thread(): void
    {
        $this->getJson("/{$this->store->username}/chat")->assertOk()->assertJsonPath('messages', []);

        // A seller's inbox should not fill with everyone who clicked the button.
        $this->assertSame(0, Conversation::count());
    }

    public function test_a_signed_in_buyer_keeps_the_history_they_started_as_a_guest(): void
    {
        $this->postJson("/{$this->store->username}/chat", ['body' => 'Pertanyaan pertama'])->assertOk();

        $token = Conversation::firstOrFail()->guest_token;
        $buyer = $this->makeUser();

        // Driven at the service, because the cookie the browser would replay is
        // encrypted in transit and the point being pinned down is the claiming
        // rule, not the transport.
        $request = Request::create('/'.$this->store->username.'/chat', 'POST', cookies: [
            'jy_chat_'.$this->store->id => $token,
        ]);
        $request->setUserResolver(fn () => $buyer);

        $chats = app(ChatService::class);
        $conversation = $chats->openForBuyer($request, $this->store);
        $chats->send($conversation, ChatMessage::BUYER, 'Lanjutannya', null, $buyer);

        // One thread, both messages — not a second thread the seller has to
        // work out is the same person.
        $this->assertSame(1, Conversation::count());
        $this->assertSame(2, ChatMessage::count());
        $this->assertSame($buyer->id, Conversation::firstOrFail()->user_id);
        $this->assertNull(Conversation::firstOrFail()->guest_token);
    }

    public function test_the_sidebar_badge_counts_only_this_creators_unread(): void
    {
        $this->postJson("/{$this->store->username}/chat", ['body' => 'Halo'])->assertOk();

        $this->actingAs($this->store->owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('chatUnread', 1));

        $this->actingAs($this->makeStore(null, ['username' => 'tokolain'])->owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('chatUnread', 0));
    }

    public function test_a_seller_cannot_open_a_thread_with_their_own_shop(): void
    {
        $this->actingAs($this->store->owner)
            ->postJson("/{$this->store->username}/chat", ['body' => 'Halo saya sendiri'])
            ->assertStatus(422);

        $this->assertSame(0, Conversation::count());
    }

    public function test_a_message_from_the_seller_never_alerts_the_seller(): void
    {
        $this->postJson("/{$this->store->username}/chat", ['body' => 'Halo'])->assertOk();

        $conversation = Conversation::firstOrFail();
        $owner = $this->store->owner;
        $before = $owner->notifications()->count();

        $this->actingAs($owner)->postJson('/dashboard/chat/'.$conversation->id, ['body' => 'Halo juga']);

        $this->assertSame($before, $owner->fresh()->notifications()->count());
    }
}
