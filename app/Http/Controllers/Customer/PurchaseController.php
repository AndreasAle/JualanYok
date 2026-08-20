<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DigitalAccess;
use App\Models\Order;
use App\Services\DigitalDeliveryService;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly DigitalDeliveryService $delivery,
        private readonly RefundService $refunds,
    ) {}

    public function index(Request $request): Response
    {
        $orders = $this->ownedOrders($request)
            ->with('store', 'items')
            ->latest()
            ->paginate(10)
            ->through(fn (Order $o) => [
                'number' => $o->number,
                'store' => $o->store->name,
                'grand_total' => (float) $o->grand_total,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'items_count' => $o->items->count(),
                'created_at' => $o->created_at->toDateTimeString(),
            ]);

        return Inertia::render('Member/Orders/Index', ['orders' => $orders]);
    }

    public function show(Request $request, Order $order): Response
    {
        $this->authorizeOrder($request, $order);

        $order->load(['items.product', 'store', 'digitalAccesses.file', 'latestPayment']);

        return Inertia::render('Member/Orders/Show', [
            'order' => [
                'number' => $order->number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_label' => $order->payment_status->label(),
                'fulfillment_label' => $order->fulfillment_status->label(),
                'tracking_number' => $order->tracking_number,
                'subtotal' => (float) $order->subtotal,
                'discount_total' => (float) $order->discount_total,
                'shipping_total' => (float) $order->shipping_total,
                'payment_fee' => (float) $order->payment_fee,
                'grand_total' => (float) $order->grand_total,
                'refundable' => $order->refundableAmount(),
                'paid_at' => $order->paid_at?->toDateTimeString(),
                'created_at' => $order->created_at->toDateTimeString(),
                'store' => ['name' => $order->store->name, 'username' => $order->store->username],
                'items' => $order->items->map(fn ($i) => [
                    'name' => $i->name,
                    'variant_name' => $i->variant_name,
                    'quantity' => $i->quantity,
                    'total' => (float) $i->total,
                    'type' => $i->product_type,
                    'post_purchase_message' => $i->product?->post_purchase_message,
                ]),
                'downloads' => $order->digitalAccesses->map(fn (DigitalAccess $a) => [
                    'id' => $a->id,
                    'name' => $a->file?->name,
                    'version' => $a->file?->version,
                    'remaining' => $a->remainingDownloads(),
                    'expires_at' => $a->expires_at?->toDateString(),
                    'available' => $a->isDownloadable(),
                    'url' => route('member.orders.download', [$order->number, $a->id]),
                ]),
            ],
        ]);
    }

    /** Issues a fresh short-lived signed URL and redirects to it. */
    public function download(Request $request, Order $order, DigitalAccess $access)
    {
        $this->authorizeOrder($request, $order);

        abort_unless($access->order_id === $order->id, 403);
        abort_unless($access->isDownloadable(), 403, 'Akses download sudah tidak berlaku.');

        return redirect($this->delivery->signedUrl($access));
    }

    public function requestRefund(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $this->refunds->request($order, $order->refundableAmount(), $data['reason'], $request->user());

        return back()->with('success', 'Permintaan refund dikirim. Kami kabari lewat email ya.');
    }

    /** Orders belong to the logged-in buyer by customer link or by email. */
    private function ownedOrders(Request $request)
    {
        $customerIds = Customer::where('user_id', $request->user()->id)->pluck('id');

        return Order::where(function ($q) use ($customerIds, $request) {
            $q->whereIn('customer_id', $customerIds)
                ->orWhere('customer_email', $request->user()->email);
        });
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        $owned = $order->customer_email === $request->user()->email
            || ($order->customer && $order->customer->user_id === $request->user()->id);

        abort_unless($owned, 403);
    }
}
