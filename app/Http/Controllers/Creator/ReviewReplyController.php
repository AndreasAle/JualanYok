<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The seller answering a review.
 *
 * A seller may reply and nothing else. They cannot edit, delete, or hide what
 * a buyer wrote — a rating a shop can erase is not a rating anyone should
 * trust. Moderation of abusive content stays with the platform.
 */
class ReviewReplyController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    public function store(Request $request, Review $review): RedirectResponse
    {
        abort_unless($review->store_id === $request->user()->store?->id, 404);

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $this->reviews->reply($review, $data['body']);

        return back()->with('success', 'Balasanmu sudah tampil di bawah ulasan itu.');
    }
}
