<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DigitalAccess;
use App\Models\Order;
use App\Services\DigitalDeliveryService;
use App\Services\RefundService;
use App\Services\FulfillmentService;
use App\Services\DisputeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly DigitalDeliveryService $delivery,
        private readonly RefundService $refunds,
        private readonly FulfillmentService $fulfillment,
        private readonly DisputeService $disputes,
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

        $order->load(['items.product', 'store', 'digitalAccesses.file', 'latestPayment', 'shipment.events', 'openDispute']);

        return Inertia::render('Member/Orders/Show', [
            'order' => [
                'number' => $order->number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_label' => $order->payment_status->label(),
                'fulfillment_label' => $order->fulfillment_status->label(),
                'tracking_number' => $order->tracking_number,
                'requires_shipping' => $order->requiresShipping(),
                'can_confirm_receipt' => $order->canBuyerConfirmReceipt(),
                'can_open_dispute' => $order->canOpenDispute(),
                'complaint_deadline_at' => $order->complaint_deadline_at?->toDateTimeString(),
                'shipment' => $order->shipment ? [
                    'courier' => $order->shipment->courier_name ?: $order->shipment->courier_company,
                    'waybill_id' => $order->shipment->waybill_id,
                    'tracking_url' => $order->shipment->tracking_url,
                    'status_label' => $order->shipment->status->label(),
                    'events' => $order->shipment->events->map(fn ($event) => [
                        'description' => $event->description ?: $event->status,
                        'location' => $event->location,
                        'event_at' => $event->event_at->toDateTimeString(),
                    ]),
                ] : null,
                'open_dispute' => $order->openDispute ? [
                    'number' => $order->openDispute->number,
                    'status_label' => $order->openDispute->status->label(),
                    'description' => $order->openDispute->description,
                    'seller_response' => $order->openDispute->seller_response,
                ] : null,
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

    public function confirmReceipt(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);
        $this->fulfillment->confirmReceived($order->load('items'));

        return back()->with('success', 'Pesanan sudah kamu terima. Terima kasih!');
    }

    public function openDispute(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);
        $data = $request->validate([
            'type' => ['required', 'in:not_received,damaged,wrong_item,incomplete,other'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'evidence' => ['nullable', 'array', 'max:5'],
            'evidence.*' => ['url', 'max:1000'],
        ]);

        $this->disputes->open($order->load('items'), $data['type'], $data['description'], $request->user(), $data['evidence'] ?? []);

        return back()->with('success', 'Komplain dibuat dan dana penjual ditahan.');
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
