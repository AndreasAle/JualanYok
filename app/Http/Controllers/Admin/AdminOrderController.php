<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = Order::with('store:id,name,username')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where(fn ($s) => $s->where('number', 'like', $term)
                    ->orWhere('customer_email', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Order $o) => [
                'number' => $o->number,
                'store' => $o->store->name,
                'store_username' => $o->store->username,
                'customer_email' => $o->customer_email,
                'grand_total' => (float) $o->grand_total,
                'platform_fee' => (float) $o->platform_fee,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'created_at' => $o->created_at->toDateTimeString(),
            ]);

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['q', 'status']),
            'statuses' => collect(OrderStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $order->load(['items', 'payments.attempts', 'store.owner', 'refunds', 'commissions.affiliate', 'customer']);

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'number' => $order->number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_label' => $order->payment_status->label(),
                'fulfillment_label' => $order->fulfillment_status->label(),
                'store' => ['name' => $order->store->name, 'username' => $order->store->username],
                'seller' => ['name' => $order->store->owner->name, 'email' => $order->store->owner->email],
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'subtotal' => (float) $order->subtotal,
                'discount_total' => (float) $order->discount_total,
                'platform_fee' => (float) $order->platform_fee,
                'payment_fee' => (float) $order->payment_fee,
                'affiliate_commission' => (float) $order->affiliate_commission,
                'grand_total' => (float) $order->grand_total,
                'seller_net' => (float) $order->seller_net,
                'refunded_total' => (float) $order->refunded_total,
                'refundable' => $order->refundableAmount(),
                'items' => $order->items->map(fn ($i) => [
                    'name' => $i->name,
                    'quantity' => $i->quantity,
                    'total' => (float) $i->total,
                ]),
                'payments' => $order->payments->map(fn ($p) => [
                    'provider' => $p->provider,
                    'reference' => $p->reference,
                    'status' => $p->status->value,
                    'amount' => (float) $p->amount,
                    'attempts' => $p->attempts->map(fn ($a) => [
                        'action' => $a->action,
                        'status' => $a->status,
                        'error' => $a->error,
                        'created_at' => $a->created_at->toDateTimeString(),
                    ]),
                ]),
                'refunds' => $order->refunds->map(fn ($r) => [
                    'id' => $r->id,
                    'amount' => (float) $r->amount,
                    'status' => $r->status,
                    'reason' => $r->reason,
                ]),
                'commissions' => $order->commissions->map(fn ($c) => [
                    'affiliate' => $c->affiliate->name,
                    'amount' => (float) $c->amount,
                    'status' => $c->status->value,
                ]),
            ],
        ]);
    }
}
