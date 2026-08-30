<?php

namespace App\Http\Controllers\Creator;

use App\Enums\PlanPaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanPayment;
use App\Services\NotificationCenterService;
use App\Services\PlanPaymentService;
use App\Support\QrImage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Creator-facing plan checkout for automatic iPaymu and legacy manual QRIS. */
class PlanPaymentController extends Controller
{
    public function __construct(
        private readonly PlanPaymentService $payments,
        private readonly NotificationCenterService $notifications,
    ) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'plan' => ['required', 'exists:plans,slug'],
            'interval' => ['required', Rule::in(['monthly', 'yearly'])],
        ]);

        $plan = Plan::where('slug', $data['plan'])->firstOrFail();

        $payment = $this->payments->open($request->user(), $plan, $data['interval']);

        if ($payment->status === PlanPaymentStatus::Failed) {
            $this->notifications->sendOnce($request->user(), [
                'type' => 'subscription.payment_failed',
                'category' => 'subscription',
                'priority' => 'high',
                'title' => 'Tagihan paket gagal dibuat',
                'message' => $payment->gateway_error ?: 'Provider pembayaran belum dapat membuat tagihan. Coba lagi atau hubungi support.',
                'url' => route('creator.subscription.pay', $payment->reference),
                'action_label' => 'Periksa pembayaran',
                'action_required' => true,
                'group_key' => 'subscription:payment-failed:'.$payment->id,
                'tone' => 'danger',
                'meta' => ['plan_payment_id' => $payment->id],
            ], 24);
        }

        return redirect()->route('creator.subscription.pay', $payment->reference);
    }

    public function show(Request $request, PlanPayment $payment): Response
    {
        $this->authorizePayment($request, $payment);

        $this->payments->expireIfLapsed($payment);
        $payment->refresh()->load('plan');

        return Inertia::render('Creator/PlanPayment', [
            'payment' => [
                'reference' => $payment->reference,
                'plan_name' => $payment->plan->name,
                'interval' => $payment->billing_interval,
                'base_amount' => (int) $payment->base_amount,
                'unique_suffix' => (int) $payment->unique_suffix,
                'amount' => (int) $payment->amount,
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'expires_at' => $payment->expires_at->toIso8601String(),
                'seconds_left' => $payment->secondsLeft(),
                'review_note' => $payment->review_note,
                'provider' => $payment->provider,
                'method' => $payment->method,
                'channel' => $payment->channel,
                'gateway_fee' => (float) $payment->gateway_fee,
                'instructions' => $payment->instructions ?? [],
                'redirect_url' => $payment->redirect_url,
                'gateway_error' => $payment->gateway_error,
                // Rendered server-side: the payload never needs a third party.
                'qr_svg' => $payment->status->isOpen()
                    ? (data_get($payment->instructions, 'qr_svg') ?? (filled($payment->qris_payload)
                        ? QrImage::svgDataUri($payment->qris_payload)
                        : null))
                    : null,
            ],
            'merchant' => $this->payments->merchantName(),
            'windowMinutes' => $this->payments->minutesToPay(),
            'automatic' => $payment->provider === 'ipaymu',
        ]);
    }

    public function checkStatus(Request $request, PlanPayment $payment)
    {
        $this->authorizePayment($request, $payment);

        $this->payments->syncStatus($payment);

        return back();
    }

    public function confirm(Request $request, PlanPayment $payment)
    {
        $this->authorizePayment($request, $payment);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $this->payments->confirm($payment, $data['note'] ?? null);

        return back()->with(
            'success',
            'Terima kasih! Pembayaranmu sedang dicek admin. Paket aktif begitu dikonfirmasi.',
        );
    }

    public function cancel(Request $request, PlanPayment $payment)
    {
        $this->authorizePayment($request, $payment);

        $this->payments->cancel($payment);

        return redirect()
            ->route('creator.subscription')
            ->with('info', 'Pembayaran dibatalkan.');
    }

    private function authorizePayment(Request $request, PlanPayment $payment): void
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
    }
}
