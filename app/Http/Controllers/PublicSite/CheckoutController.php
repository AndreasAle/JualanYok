<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaymentManager;
use App\Payments\PaymentResult;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly PaymentManager $manager,
        private readonly PaymentService $payments,
    ) {}

    public function show(Request $request, Order $order): Response
    {
        $order->load(['items', 'store.theme', 'latestPayment']);

        return Inertia::render('Checkout/Show', [
            'order' => $this->orderPayload($order),
            'methods' => $this->manager->availableMethods(),
            'payment' => $order->latestPayment ? $this->paymentPayload($order->latestPayment) : null,
            'demo' => (bool) config('jualanyok.demo.enabled'),
        ]);
    }

    public function pay(Request $request, Order $order)
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:40'],
            'method' => ['required', 'string', 'max:40'],
            'channel' => ['nullable', 'string', 'max:40'],
        ]);

        $this->payments->createPayment($order, $data['provider'], $data['method'], $data['channel'] ?? null);

        return redirect()->route('checkout.status', $order->number);
    }

    public function status(Request $request, Order $order): Response
    {
        $order->load(['items', 'store', 'latestPayment']);

        return Inertia::render('Checkout/Status', [
            'order' => $this->orderPayload($order),
            'payment' => $order->latestPayment ? $this->paymentPayload($order->latestPayment) : null,
            'demo' => (bool) config('jualanyok.demo.enabled'),
            'memberUrl' => route('otp.create'),
        ]);
    }

    public function retry(Request $request, Order $order)
    {
        abort_unless($order->isPayable(), 409, 'Pesanan ini sudah tidak bisa dibayar.');

        return redirect()->route('checkout.show', $order->number);
    }

    /**
     * Development-only stand-in for a gateway callback. Guarded by DEMO_MODE so
     * it can never settle a payment in production.
     */
    public function simulate(Request $request, Payment $payment)
    {
        abort_unless(config('jualanyok.demo.enabled'), 404);
        abort_unless($payment->provider === 'mock', 404);

        $outcome = $request->string('outcome', 'paid')->toString();

        $result = new PaymentResult(
            status: $outcome === 'paid' ? PaymentStatus::Paid : PaymentStatus::Failed,
            reference: $payment->reference,
            amount: (float) $payment->amount,
            paidAt: $outcome === 'paid' ? now() : null,
            eventId: 'sim-'.$payment->id.'-'.$outcome,
        );

        $this->payments->applyResult($result, 'mock');

        return redirect()
            ->route('checkout.status', $payment->order->number)
            ->with($outcome === 'paid' ? 'success' : 'error',
                $outcome === 'paid' ? 'Pembayaran berhasil disimulasikan.' : 'Pembayaran gagal disimulasikan.');
    }

    private function orderPayload(Order $order): array
    {
        return [
            'number' => $order->number,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'payment_status' => $order->payment_status->value,
            'fulfillment_status' => $order->fulfillment_status->value,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount_total,
            'shipping_total' => (float) $order->shipping_total,
            'tax_total' => (float) $order->tax_total,
            'payment_fee' => (float) $order->payment_fee,
            'grand_total' => (float) $order->grand_total,
            'coupon_code' => $order->coupon_code,
            'expires_at' => $order->expires_at?->toIso8601String(),
            'is_payable' => $order->isPayable(),
            'items' => $order->items->map(fn ($i) => [
                'name' => $i->name,
                'variant_name' => $i->variant_name,
                'quantity' => $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'total' => (float) $i->total,
            ]),
            'store' => [
                'name' => $order->store->name,
                'username' => $order->store->username,
                'avatar_url' => $order->store->avatarUrl(),
            ],
        ];
    }

    private function paymentPayload(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'provider' => $payment->provider,
            'method' => $payment->method,
            'channel' => $payment->channel,
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'amount' => (float) $payment->amount,
            'instructions' => $payment->instructions ?? [],
            'redirect_url' => $payment->redirect_url,
            'expires_at' => $payment->expires_at?->toIso8601String(),
            'is_open' => $payment->isOpen(),
        ];
    }
}
