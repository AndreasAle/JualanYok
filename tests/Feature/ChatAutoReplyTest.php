<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop's automatic first reply, and who is at the desk.
 *
 * A buyer who asks at midnight and hears nothing assumes the shop is
 * abandoned. One sentence keeps them — but only if it is honest about being
 * automatic, and only if the little green dot beside it means what it says.
 */
class ChatAutoReplyTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        $this->store = $this->makeStore();
        $this->store->forceFill([
            'is_published' => true,
            'chat_auto_reply_enabled' => true,
            'chat_auto_reply' => 'Halo! Dibalas maksimal 1x24 jam ya.',
        ])->save();
    }

    public function test_the_first_question_gets_an_answer_immediately(): void
    {
        $this->postJson("/{$this->store->username}/chat", ['body' => 'Masih ada stok?'])->assertOk();

        $reply = ChatMessage::where('sender', ChatMessage::SELLER)->firstOrFail();

        $this->assertSame('Halo! Dibalas maksimal 1x24 jam ya.', $reply->body);
        $this->assertTrue($reply->is_auto, 'Balasan otomatis harus ditandai, bukan disamarkan.');
    }

    public function test_it_is_said_once_and_not_after_every_question(): void
    {
        $this->postJson("/{$this->store->username}/chat", ['body' => 'Halo'])->assertOk();

        $conversation = Conversation::firstOrFail();
        $chats = app(\App\Services\ChatService::class);
        $chats->send($conversation, ChatMessage::BUYER, 'Halo lagi');

        // Repeating it would read as being ignored by a machine, twice.
        $this->assertSame(1, ChatMessage::where('is_auto', true)->count());
    }

    public function test_it_stays_quiet_once_a_person_has_answered(): void
    {
        $this->postJson("/{$this->store->username}/chat", ['body' => 'Halo'])->assertOk();

        $conversation = Conversation::firstOrFail();
        ChatMessage::where('is_auto', true)->delete();

        $this->actingAs($this->store->owner)
            ->postJson("/dashboard/chat/{$conversation->id}", ['body' => 'Ready kak']);

        // Driven directly: `actingAs` sticks for the rest of the test, and the
        // shop cannot post into its own thread as a buyer.
        app(\App\Services\ChatService::class)->send($conversation->fresh(), ChatMessage::BUYER, 'Oke');

        $this->assertSame(0, ChatMessage::where('is_auto', true)->count());
    }

    public function test_a_shop_that_turned_it_off_says_nothing(): void
    {
        $this->store->forceFill(['chat_auto_reply_enabled' => false])->save();

        $this->postJson("/{$this->store->username}/chat", ['body' => 'Halo'])->assertOk();

        $this->assertSame(0, ChatMessage::where('sender', ChatMessage::SELLER)->count());
    }

    public function test_the_seller_edits_it_from_their_inbox(): void
    {
        $this->actingAs($this->store->owner)
            ->put('/dashboard/chat/balasan-otomatis', [
                'enabled' => true,
                'message' => 'Fast respon jam 9 pagi sampai 9 malam.',
            ])
            ->assertRedirect();

        $this->assertSame('Fast respon jam 9 pagi sampai 9 malam.', $this->store->fresh()->chat_auto_reply);
    }

    public function test_it_cannot_be_switched_on_with_nothing_to_say(): void
    {
        $this->actingAs($this->store->owner)
            ->put('/dashboard/chat/balasan-otomatis', ['enabled' => true, 'message' => ''])
            ->assertSessionHasErrors('message');
    }

    public function test_the_shop_reads_as_online_only_while_the_inbox_is_open(): void
    {
        // Nobody has looked at the inbox yet.
        $this->getJson("/{$this->store->username}/chat")
            ->assertOk()
            ->assertJsonPath('seller.online', false);

        $this->actingAs($this->store->owner)->get('/dashboard/chat')->assertOk();

        $this->assertNotNull($this->store->fresh()->chat_seen_at);

        $this->getJson("/{$this->store->username}/chat")
            ->assertOk()
            ->assertJsonPath('seller.online', true);
    }

    public function test_an_old_visit_is_reported_as_a_time_not_as_online(): void
    {
        $this->store->forceFill(['chat_seen_at' => now()->subHours(5)])->save();

        $seller = $this->getJson("/{$this->store->username}/chat")->assertOk()->json('seller');

        // A dot that lies turns "no reply yet" into "they are ignoring me".
        $this->assertFalse($seller['online']);
        $this->assertStringContainsString('Aktif', $seller['label']);
    }
}
