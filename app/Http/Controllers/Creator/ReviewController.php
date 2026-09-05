<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The seller's view of what buyers said.
 *
 * Sorted by what needs an answer rather than by date: an unanswered one-star
 * review is the most expensive thing on this page, and burying it under
 * yesterday's five-star ones is how it stays unanswered.
 */
class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $store = $request->user()->store;

        $filter = in_array($request->query('saring'), ['perlu-dibalas', 'rendah'], true)
            ? $request->query('saring')
            : 'semua';

        $reviews = Review::where('store_id', $store->id)
            ->where('status', Review::PUBLISHED)
            ->with(['product:id,name,slug,thumbnail_path', 'author:id,name,avatar_path'])
            ->when($filter === 'perlu-dibalas', fn ($q) => $q->whereNull('seller_reply'))
            ->when($filter === 'rendah', fn ($q) => $q->where('rating', '<=', 3))
            ->orderByRaw('seller_reply IS NOT NULL')
            ->orderBy('rating')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Review $review) => [
                'id' => $review->id,
                'name' => $review->displayName(),
                'avatar_url' => $review->avatarUrl(),
                'rating' => $review->rating,
                'body' => $review->body,
                'variant_label' => $review->variant_label,
                'media' => $review->mediaUrls(),
                'created_at' => $review->created_at->translatedFormat('d M Y'),
                'seller_reply' => $review->seller_reply,
                'product' => [
                    'name' => $review->product?->name,
                    'thumbnail_url' => $review->product?->thumbnailUrl(),
                    'url' => $review->product
                        ? route('storefront.product', [$store->username, $review->product->slug])
                        : null,
                ],
            ]);

        $base = Review::where('store_id', $store->id)->where('status', Review::PUBLISHED);

        return Inertia::render('Creator/Reviews', [
            'reviews' => $reviews,
            'filter' => $filter,
            'stats' => [
                'total' => (clone $base)->count(),
                'unanswered' => (clone $base)->whereNull('seller_reply')->count(),
                'low' => (clone $base)->where('rating', '<=', 3)->count(),
                'average' => round((float) (clone $base)->avg('rating'), 1),
            ],
        ]);
    }
}
