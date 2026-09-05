<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The buyer writing a review.
 *
 * Which order is being reviewed is checked against what this account actually
 * bought, so an order number typed into the address bar reaches nothing.
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    public function store(Request $request, Order $order): RedirectResponse
    {
        $this->assertOwned($request, $order);

        $data = $request->validate([
            'order_item_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:2000'],
            'is_anonymous' => ['nullable', 'boolean'],
            'media' => ['nullable', 'array', 'max:'.ReviewService::MAX_MEDIA],
            'media.*' => [
                'file',
                // Checked against the file's real type, not its name: an
                // executable renamed .jpg must not land in a public folder.
                'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm',
                'max:'.(20 * 1024),
            ],
        ], [
            'media.*.mimetypes' => 'Hanya foto (JPG, PNG, WEBP) atau video (MP4, MOV, WEBM).',
            'media.*.max' => 'Maksimal 20 MB per file.',
            'media.max' => 'Maksimal '.ReviewService::MAX_MEDIA.' foto atau video.',
        ]);

        $item = OrderItem::where('order_id', $order->id)->findOrFail($data['order_item_id']);

        $this->reviews->create(
            $order,
            $item,
            $request->user(),
            $data,
            $request->file('media', []),
        );

        return back()->with('success', 'Makasih! Ulasanmu sudah tayang di halaman produk.');
    }

    private function assertOwned(Request $request, Order $order): void
    {
        $customerIds = Customer::where('user_id', $request->user()->id)->pluck('id');

        abort_unless(
            $customerIds->contains($order->customer_id)
                || $order->customer_email === $request->user()->email,
            404,
        );
    }
}
