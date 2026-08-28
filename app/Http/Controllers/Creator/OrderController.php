<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DisputeService;
use App\Services\FulfillmentService;
use App\Services\RefundService;
use App\Services\ShippingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly FulfillmentService $fulfillment,
        private readonly RefundService $refunds,
        private readonly ShippingService $shipping,
        private readonly DisputeService $disputes,
    ) {}

    public function index(Request $request): Response
    {
        $store = $request->user()->store;

        $orders = Order::where('store_id', $store->id)
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where(fn ($sub) => $sub->where('number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_email', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->withCount('items')
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Order $o) => [
                'number' => $o->number,
                'customer_name' => $o->customer_name,
                'customer_email' => $o->customer_email,
                'grand_total' => (float) $o->grand_total,
                'seller_net' => (float) $o->seller_net,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'payment_status' => $o->payment_status->value,
                'fulfillment_status' => $o->fulfillment_status->value,
                'fulfillment_label' => $o->fulfillment_status->label(),
                'items_count' => $o->items_count,
                'created_at' => $o->created_at->toDateTimeString(),
                'created_human' => $o->created_at->diffForHumans(),
            ]);

        return Inertia::render('Creator/Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['q', 'status']),
            'summary' => [
                'total' => Order::where('store_id', $store->id)->paid()->count(),
                'awaiting_payment' => Order::where('store_id', $store->id)
                    ->where('status', 'PENDING_PAYMENT')->count(),
                'to_ship' => Order::where('store_id', $store->id)
                    ->paid()->where('fulfillment_status', 'UNFULFILLED')->count(),
            ],
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $this->authorizeOrder($request, $order);

        $order->load(['items.product', 'items.variant', 'payments', 'customer', 'refunds', 'digitalAccesses', 'commissions.affiliate', 'shipment.events', 'openDispute']);

        return Inertia::render('Creator/Orders/Show', [
            'order' => [
                'number' => $order->number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_status' => $order->payment_status->value,
                'payment_label' => $order->payment_status->label(),
                'fulfillment_status' => $order->fulfillment_status->value,
                'fulfillment_label' => $order->fulfillment_status->label(),
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'customer_phone' => $order->customer_phone,
                'customer_note' => $order->customer_note,
                'custom_fields' => $order->custom_fields ?? [],
                'shipping_address' => $order->shipping_address,
                'shipping_method' => $order->shipping_method,
                'tracking_number' => $order->tracking_number,
                'shipping_provider' => $order->shipping_provider,
                'shipping_service' => $order->shipping_service,
                'shipping_courier' => $order->shipping_courier,
                'shipment' => $order->shipment ? [
                    'id' => $order->shipment->id,
                    'provider' => $order->shipment->provider,
                    'status' => $order->shipment->status->value,
                    'status_label' => $order->shipment->status->label(),
                    'waybill_id' => $order->shipment->waybill_id,
                    'tracking_url' => $order->shipment->tracking_url,
                    'last_error' => $order->shipment->last_error,
                    'events' => $order->shipment->events->map(fn ($event) => [
                        'description' => $event->description ?: $event->status,
                        'location' => $event->location,
                        'event_at' => $event->event_at->toDateTimeString(),
                    ]),
                ] : null,
                'open_dispute' => $order->openDispute ? [
                    'id' => $order->openDispute->id,
                    'number' => $order->openDispute->number,
                    'type' => $order->openDispute->type,
                    'description' => $order->openDispute->description,
                    'status_label' => $order->openDispute->status->label(),
                    'seller_response' => $order->openDispute->seller_response,
                ] : null,
                'requires_shipping' => $order->requiresShipping(),
                'subtotal' => (float) $order->subtotal,
                'discount_total' => (float) $order->discount_total,
                'tax_total' => (float) $order->tax_total,
                'shipping_total' => (float) $order->shipping_total,
                'shipping_cost_actual' => (float) $order->shipping_cost_actual,
                'shipping_variance' => (float) $order->shipping_variance,
                'commission_base' => (float) $order->commission_base,
                'platform_fee' => (float) $order->platform_fee,
                'platform_fee_rate' => (float) $order->platform_fee_rate,
                'payment_fee' => (float) $order->payment_fee,
                'gateway_fee_estimated' => (float) $order->gateway_fee_estimated,
                'gateway_fee_actual' => (float) $order->gateway_fee_actual,
                'gateway_fee_bearer' => $order->gateway_fee_bearer,
                'affiliate_commission' => (float) $order->affiliate_commission,
                'grand_total' => (float) $order->grand_total,
                'seller_net' => (float) $order->seller_net,
                'reserve_amount' => (float) $order->reserve_amount,
                'reserve_rate' => (float) $order->reserve_rate,
                'debt_offset' => (float) $order->debt_offset,
                'funds_release_at' => $order->funds_release_at?->toDateTimeString(),
                'reserve_release_at' => $order->reserve_release_at?->toDateTimeString(),
                'settlement_version' => (int) $order->settlement_version,
                'refunded_total' => (float) $order->refunded_total,
                'refundable' => $order->refundableAmount(),
                'coupon_code' => $order->coupon_code,
                'affiliate_code' => $order->affiliate_code,
                'paid_at' => $order->paid_at?->toDateTimeString(),
                'created_at' => $order->created_at->toDateTimeString(),
                'items' => $order->items->map(fn ($i) => [
                    'name' => $i->name,
                    'variant_name' => $i->variant_name,
                    'quantity' => $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                    'total' => (float) $i->total,
                    'type' => $i->product_type,
                    'commission_amount' => (float) $i->commission_amount,
                ]),
                'payments' => $order->payments->map(fn ($p) => [
                    'provider' => $p->provider,
                    'method' => $p->method,
                    'channel' => $p->channel,
                    'status' => $p->status->value,
                    'status_label' => $p->status->label(),
                    'amount' => (float) $p->amount,
                    'fee' => (float) $p->fee,
                    'fee_source' => $p->fee_source,
                    'settlement_days' => (int) $p->settlement_days,
                    'reference' => $p->reference,
                    'paid_at' => $p->paid_at?->toDateTimeString(),
                ]),
                'refunds' => $order->refunds->map(fn ($r) => [
                    'id' => $r->id,
                    'amount' => (float) $r->amount,
                    'status' => $r->status,
                    'reason' => $r->reason,
                    'created_at' => $r->created_at->toDateTimeString(),
                ]),
            ],
        ]);
    }

    public function ship(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);

        $data = $request->validate([
            'tracking_number' => ['required', 'string', 'max:64'],
            'courier' => ['nullable', 'string', 'max:60'],
        ]);

        $this->fulfillment->markShipped($order, $data['tracking_number'], $data['courier'] ?? null);

        return back()->with('success', 'Pesanan ditandai sudah dikirim.');
    }

    public function complete(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);

        $this->fulfillment->markDelivered($order);

        return back()->with('success', 'Pesanan selesai.');
    }

    public function requestRefund(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->refunds->request($order, (float) $data['amount'], $data['reason'], $request->user());

        return back()->with('success', 'Pengajuan refund dikirim ke tim JualanYok.');
    }

    public function bookShipment(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);
        $shipment = $this->shipping->createShipment($order);

        return back()->with('success', $shipment->provider === 'manual'
            ? 'Pesanan siap dikirim. Masukkan resi setelah paket diserahkan.'
            : 'Kurir berhasil dipesan. Pantau penjemputan dari halaman ini.');
    }

    public function syncShipment(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);
        abort_unless($order->shipment, 404);
        $this->shipping->sync($order->shipment);

        return back()->with('success', 'Status kurir sudah diperbarui.');
    }

    public function respondDispute(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);
        abort_unless($order->openDispute, 404);
        $data = $request->validate(['response' => ['required', 'string', 'min:20', 'max:2000']]);
        $this->disputes->sellerRespond($order->openDispute, $request->user(), $data['response']);

        return back()->with('success', 'Respons komplain sudah dikirim.');
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($order->store_id === $request->user()->store->id, 403);
    }
}
