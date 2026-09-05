<?php

namespace App\Http\Controllers;

use App\Models\ChatAttachment;
use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Handing back a file someone sent in a chat.
 *
 * The link is signed and short-lived, but that alone would mean anyone holding
 * a copied URL could read it. So membership of the conversation is checked as
 * well: the shop that owns it, the buyer who signed in, or the guest whose
 * cookie matches the thread. Two independent gates, because what travels
 * through a chat is receipts and sometimes an ID.
 */
class ChatAttachmentController extends Controller
{
    public function __construct(private readonly ChatService $chats) {}

    public function __invoke(Request $request, ChatAttachment $attachment): StreamedResponse
    {
        $conversation = $attachment->message?->conversation;

        abort_unless($conversation && $this->participates($request, $conversation), 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->response(
            $attachment->path,
            $attachment->name,
            [
                'Content-Type' => $attachment->mime,
                /*
                 * Images and video play in place; everything else downloads.
                 * A PDF or a document rendered inline from our own origin is a
                 * page that can act as us, so those never display here.
                 */
                'Content-Disposition' => ($attachment->kind === 'file' ? 'attachment' : 'inline')
                    .'; filename="'.addslashes($attachment->name).'"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=3600',
            ],
        );
    }

    private function participates(Request $request, Conversation $conversation): bool
    {
        $user = $request->user();

        if ($user && $conversation->store->user_id === $user->id) {
            return true;
        }

        if ($user && $conversation->user_id === $user->id) {
            return true;
        }

        $token = (string) $request->cookie("jy_chat_{$conversation->store_id}");

        return $token !== '' && $conversation->guest_token === $token;
    }
}
