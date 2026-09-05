<?php

namespace Tests\Feature;

use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Files sent inside a conversation.
 *
 * What travels through a chat is receipts, transfer screenshots, sometimes an
 * ID. So nothing lands in a public folder, and reading one takes both a signed
 * link and membership of the thread — either alone is a bet, not a guarantee.
 */
class ChatAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        Storage::fake('local');
        Storage::fake('public');

        $this->store = $this->makeStore();
        $this->store->forceFill(['is_published' => true])->save();
    }

    public function test_a_buyer_can_send_a_photo_and_a_document(): void
    {
        $this->post("/{$this->store->username}/chat", [
            'body' => 'Ini bukti transfernya kak',
            'files' => [
                UploadedFile::fake()->image('bukti.jpg'),
                UploadedFile::fake()->create('invoice.pdf', 200, 'application/pdf'),
            ],
        ])->assertOk();

        $files = ChatAttachment::orderBy('id')->get();

        $this->assertCount(2, $files);
        $this->assertSame(['image', 'file'], $files->pluck('kind')->all());

        foreach ($files as $file) {
            // Never public. A random name in a public folder is obscurity.
            Storage::disk('local')->assertExists($file->path);
            Storage::disk('public')->assertMissing($file->path);
            $this->assertStringNotContainsString('bukti.jpg', $file->path);
        }
    }

    public function test_a_message_may_be_only_a_file(): void
    {
        $this->post("/{$this->store->username}/chat", [
            'files' => [UploadedFile::fake()->image('warna.png')],
        ])->assertOk();

        // The inbox still has to say something happened.
        $this->assertSame('Mengirim foto', Conversation::firstOrFail()->last_message_preview);
    }

    public function test_a_message_with_neither_words_nor_files_is_refused(): void
    {
        $this->postJson("/{$this->store->username}/chat", ['body' => '   '])
            ->assertJsonValidationErrors('body');

        $this->assertSame(0, Conversation::count());
    }

    public function test_an_executable_wearing_an_image_name_is_refused(): void
    {
        $this->postJson("/{$this->store->username}/chat", [
            'body' => 'Nih',
            'files' => [UploadedFile::fake()->create('payload.jpg', 8, 'application/x-httpd-php')],
        ])->assertJsonValidationErrors('files.0');

        $this->assertSame(0, ChatAttachment::count());
    }

    public function test_each_kind_is_held_to_its_own_size(): void
    {
        // Fine for a video, far past what a screenshot should ever be.
        $this->postJson("/{$this->store->username}/chat", [
            'body' => 'Foto besar',
            'files' => [UploadedFile::fake()->create('besar.jpg', 12 * 1024, 'image/jpeg')],
        ])->assertJsonValidationErrors('files.0');
    }

    public function test_an_outsider_cannot_read_an_attachment_even_with_the_link(): void
    {
        $this->post("/{$this->store->username}/chat", [
            'files' => [UploadedFile::fake()->image('rahasia.jpg')],
        ])->assertOk();

        $attachment = ChatAttachment::firstOrFail();
        $link = URL::signedRoute('chat.attachment', ['attachment' => $attachment->id], now()->addHour());

        // A correctly signed link, held by someone who is not in the thread.
        $this->flushSession();
        $this->get($link)->assertNotFound();
    }

    public function test_the_shop_that_owns_the_thread_can_read_it(): void
    {
        $this->post("/{$this->store->username}/chat", [
            'files' => [UploadedFile::fake()->image('bukti.jpg')],
        ])->assertOk();

        $attachment = ChatAttachment::firstOrFail();
        $link = URL::signedRoute('chat.attachment', ['attachment' => $attachment->id], now()->addHour());

        $this->actingAs($this->store->owner)->get($link)->assertOk();
    }

    public function test_an_unsigned_link_is_refused(): void
    {
        $this->post("/{$this->store->username}/chat", [
            'files' => [UploadedFile::fake()->image('bukti.jpg')],
        ])->assertOk();

        $attachment = ChatAttachment::firstOrFail();

        $this->actingAs($this->store->owner)
            ->get("/chat/lampiran/{$attachment->id}")
            ->assertForbidden();
    }

    public function test_a_document_is_handed_back_as_a_download_never_rendered(): void
    {
        $this->post("/{$this->store->username}/chat", [
            'files' => [UploadedFile::fake()->create('surat.pdf', 50, 'application/pdf')],
        ])->assertOk();

        $attachment = ChatAttachment::firstOrFail();
        $link = URL::signedRoute('chat.attachment', ['attachment' => $attachment->id], now()->addHour());

        $response = $this->actingAs($this->store->owner)->get($link)->assertOk();

        // A document rendered inline from our own origin is a page that can act
        // as us, so it downloads instead.
        $this->assertStringStartsWith('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_the_seller_can_answer_with_a_product_they_actually_sell(): void
    {
        $this->post("/{$this->store->username}/chat", ['body' => 'Ada stok?'])->assertOk();

        $conversation = Conversation::firstOrFail();
        $product = $this->makeProduct($this->store, ['name' => 'Piyama Busui']);

        $this->actingAs($this->store->owner)
            ->post("/dashboard/chat/{$conversation->id}", [
                'body' => 'Ready kak, langsung checkout aja',
                'product_id' => $product->id,
            ])
            ->assertOk();

        $reply = ChatMessage::where('sender', ChatMessage::SELLER)->latest('id')->firstOrFail();

        $this->assertSame('Piyama Busui', $reply->context['label']);
        $this->assertStringContainsString("/{$this->store->username}/p/{$product->slug}", $reply->context['url']);
    }

    public function test_a_seller_cannot_recommend_a_product_from_another_shop(): void
    {
        $this->post("/{$this->store->username}/chat", ['body' => 'Halo'])->assertOk();

        $conversation = Conversation::firstOrFail();
        $foreign = $this->makeProduct($this->makeStore(null, ['username' => 'tokolain']));

        $this->actingAs($this->store->owner)
            ->post("/dashboard/chat/{$conversation->id}", ['body' => 'Coba ini', 'product_id' => $foreign->id])
            ->assertOk();

        $this->assertNull(ChatMessage::where('sender', ChatMessage::SELLER)->latest('id')->firstOrFail()->context);
    }
}
