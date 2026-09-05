<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reviews, and the rule that makes them worth reading.
 *
 * A star rating is only information if it cost something to leave. Here that
 * cost is a purchase: a review hangs off the order line it came from, so it can
 * only be written by whoever paid for that line, exactly once, and a shop
 * cannot manufacture praise for itself.
 */
class ReviewService
{
    public const MAX_MEDIA = 5;

    /**
     * The lines from an order that can still be reviewed.
     *
     * @return \Illuminate\Support\Collection<int, OrderItem>
     */
    public function reviewableItems(Order $order)
    {
        if (! $order->status->isSettled()) {
            return collect();
        }

        $order->loadMissing(['items.product', 'items.variant']);

        $reviewed = Review::whereIn('order_item_id', $order->items->pluck('id'))->pluck('order_item_id');

        return $order->items
            ->filter(fn (OrderItem $item) => $item->product_id !== null && ! $reviewed->contains($item->id))
            ->values();
    }

    /**
     * @param  array<int, UploadedFile>  $media
     */
    public function create(Order $order, OrderItem $item, ?User $author, array $data, array $media = []): Review
    {
        $this->assertReviewable($order, $item);

        return DB::transaction(function () use ($order, $item, $author, $data, $media) {
            $review = Review::create([
                'store_id' => $order->store_id,
                'product_id' => $item->product_id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'user_id' => $author?->id,
                'author_name' => $author?->name ?: ($order->customer_name ?: 'Pembeli'),
                'rating' => (int) $data['rating'],
                'body' => $this->clean($data['body'] ?? null),
                'variant_label' => $item->variant?->name,
                'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            ]);

            foreach (array_slice($media, 0, self::MAX_MEDIA) as $position => $file) {
                $kind = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';

                ReviewMedia::create([
                    'review_id' => $review->id,
                    // Laravel names the file itself, so the uploader's own
                    // filename never reaches the disk or a public URL.
                    'path' => $file->store("stores/{$order->store_id}/reviews", config('jualanyok.uploads.disk')),
                    'kind' => $kind,
                    'position' => $position,
                ]);
            }

            $this->recount($item->product_id);

            return $review->load('media');
        });
    }

    /** One reply per review, from the shop that sold the thing. */
    public function reply(Review $review, string $body): Review
    {
        $review->forceFill([
            'seller_reply' => $this->clean($body),
            'seller_replied_at' => now(),
        ])->save();

        return $review;
    }

    /**
     * Recomputes a product's average from scratch.
     *
     * Deliberately not an incremental += : hidden reviews, deleted orders and
     * edits all move the average, and a counter that drifts from the rows it
     * summarises is worse than no counter at all.
     */
    public function recount(int $productId): void
    {
        $stats = Review::where('product_id', $productId)
            ->where('status', Review::PUBLISHED)
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')
            ->first();

        Product::whereKey($productId)->update([
            'rating_count' => (int) ($stats->total ?? 0),
            'rating_avg' => $stats && $stats->total > 0 ? round((float) $stats->average, 2) : null,
        ]);
    }

    /**
     * How a product's stars break down, for the summary bars.
     *
     * @return array{average: float, total: int, breakdown: array<int, int>, with_media: int, with_comment: int}
     */
    public function summary(Product $product): array
    {
        $rows = Review::where('product_id', $product->id)
            ->where('status', Review::PUBLISHED)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $total = (int) $rows->sum();

        // Weighted by hand: Collection::sum hands its callback the value only,
        // and the star is the key here.
        $weighted = 0;

        foreach ($rows as $star => $count) {
            $weighted += (int) $star * (int) $count;
        }

        return [
            'average' => $total > 0 ? round($weighted / $total, 1) : 0.0,
            'total' => $total,
            'breakdown' => collect(range(5, 1))
                ->mapWithKeys(fn (int $star) => [$star => (int) ($rows[$star] ?? 0)])
                ->all(),
            'with_media' => Review::where('product_id', $product->id)
                ->where('status', Review::PUBLISHED)
                ->whereHas('media')
                ->count(),
            'with_comment' => Review::where('product_id', $product->id)
                ->where('status', Review::PUBLISHED)
                ->whereNotNull('body')
                ->where('body', '!=', '')
                ->count(),
        ];
    }

    private function assertReviewable(Order $order, OrderItem $item): void
    {
        if ($item->order_id !== $order->id) {
            throw ValidationException::withMessages(['rating' => 'Item ini bukan bagian dari pesananmu.']);
        }

        if (! $order->status->isSettled()) {
            throw ValidationException::withMessages([
                'rating' => 'Ulasan bisa ditulis setelah pesanan dibayar.',
            ]);
        }

        if (Review::where('order_item_id', $item->id)->exists()) {
            throw ValidationException::withMessages(['rating' => 'Produk ini sudah kamu ulas.']);
        }
    }

    private function clean(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        // Control characters are never typed on purpose and are a cheap way to
        // make text render deceptively.
        $body = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $body) ?? '';
        $body = trim($body);

        return $body === '' ? null : mb_substr($body, 0, 2000);
    }
}
