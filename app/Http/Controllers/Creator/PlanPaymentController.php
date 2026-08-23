<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanPayment;
use App\Services\PlanPaymentService;
use App\Support\QrImage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The subscriber's side of manual QRIS billing: show a QR for an exact amount,
 * then wait for an admin to confirm the transfer landed.
 */
class PlanPaymentController extends Controller
{
    public function __construct(private readonly PlanPaymentService $payments) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'plan' => ['required', 'exists:plans,slug'],
            'interval' => ['required', Rule::in(['monthly', 'yearly'])],
        ]);

        $plan = Plan::where('slug', $data['plan'])->firstOrFail();

        $payment = $this->payments->open($request->user(), $plan, $data['interval']);

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
                // Rendered server-side: the payload never needs a third party.
                'qr_svg' => $payment->status->isOpen()
                    ? QrImage::svgDataUri($payment->qris_payload)
                    : null,
            ],
            'merchant' => $this->payments->merchantName(),
            'windowMinutes' => $this->payments->minutesToPay(),
        ]);
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
