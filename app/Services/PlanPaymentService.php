<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\PlanPaymentStatus;
use App\Models\Plan;
use App\Models\PlanPayment;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Payments\PaymentManager;
use App\Payments\PaymentResult;
use App\Payments\Providers\IpaymuProvider;
use App\Support\Money;
use App\Support\Qris;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual QRIS billing for SaaS plans.
 *
 * The wallet provider gives us no callback, so a payment is identified purely
 * by its exact rupiah amount: the plan price plus a small per-payer suffix. An
 * admin reads the incoming mutation, finds the matching amount, and approves.
 *
 * That only works if an open amount belongs to exactly one payer, which the
 * database enforces through a unique index on `claimable_amount` — not by
 * hoping a random suffix does not collide.
 */
class PlanPaymentService
{
    /** Suffix range added to the price, e.g. Rp 49.000 becomes Rp 49.347. */
    private const SUFFIX_MIN = 1;

    private const SUFFIX_MAX = 999;

    /** Distinct suffixes to try before admitting the range is saturated. */
    private const MAX_ATTEMPTS = 40;

    public function __construct(
        private readonly PlanService $plans,
        private readonly PaymentManager $manager,
        private readonly MarketplaceLedgerService $marketplaceLedger,
        private readonly NotificationCenterService $notifications,
    ) {}

    public function enabled(): bool
    {
        return $this->usesIpaymu()
            || ((bool) config('payments.qris.enabled') && $this->staticPayload() !== null);
    }

    /**
     * A flag on its own is not a working gateway.
     *
     * Flipping IPAYMU_ENABLED without the keys used to switch the whole billing
     * flow over to a provider that could not create a single bill. Falling back
     * to manual QRIS is a working checkout; an iPaymu screen that always fails
     * is not.
     */
    public function usesIpaymu(): bool
    {
        $config = config('payments.providers.ipaymu', []);

        return (bool) ($config['enabled'] ?? false)
            && filled($config['va'] ?? null)
            && filled($config['api_key'] ?? null);
    }

    public function automatic(): bool
    {
        return $this->usesIpaymu();
    }

    public function providerName(): string
    {
        return $this->usesIpaymu() ? 'iPaymu' : 'QRIS manual';
    }

    public function merchantName(): ?string
    {
        if ($this->usesIpaymu()) {
            return null;
        }

        $payload = $this->staticPayload();

        return $payload ? Qris::merchantName($payload) : null;
    }

    public function minutesToPay(): int
    {
        if ($this->usesIpaymu()) {
            return 5;
        }

        return (int) config('payments.qris.window_minutes', 30);
    }

    /**
     * Opens a payment for a plan. Any earlier open payment by the same user is
     * released first, so a person cannot sit on several reserved amounts.
     */
    public function open(User $user, Plan $plan, string $interval = 'monthly'): PlanPayment
    {
        if (! $this->enabled()) {
            throw ValidationException::withMessages([
                'plan' => 'Pembayaran QRIS belum aktif. Hubungi admin.',
            ]);
        }

        $base = (int) round($interval === 'yearly' ? $plan->price_yearly : $plan->price_monthly);

        if ($base < 1) {
            throw ValidationException::withMessages([
                'plan' => 'Paket ini gratis, tidak perlu pembayaran.',
            ]);
        }

        if ($this->usesIpaymu()) {
            return $this->openIpaymu($user, $plan, $interval, $base);
        }

        $this->releaseOpenPaymentsFor($user);

        return $this->allocate($user, $plan, $interval, $base);
    }

    /** The payer states they have transferred; the amount stays reserved. */
    public function confirm(PlanPayment $payment, ?string $note = null): PlanPayment
    {
        if ($payment->provider === 'ipaymu') {
            throw ValidationException::withMessages([
                'payment' => 'Pembayaran iPaymu dikonfirmasi otomatis. Gunakan tombol Cek Status bila belum berubah.',
            ]);
        }

        $this->expireIfLapsed($payment);

        if ($payment->status !== PlanPaymentStatus::Pending) {
            throw ValidationException::withMessages([
                'payment' => 'Pembayaran ini sudah tidak bisa dikonfirmasi.',
            ]);
        }

        $payment->update([
            'status' => PlanPaymentStatus::AwaitingReview,
            'confirmed_at' => now(),
            'payer_note' => $note,
        ]);

        return $payment->fresh();
    }

    /**
     * Admin confirms the money arrived, which activates the plan.
     *
     * Idempotent: approving an already-paid payment returns it untouched rather
     * than starting a second subscription period.
     */
    public function approve(PlanPayment $payment, User $admin, ?string $note = null): PlanPayment
    {
        if ($payment->status->isSettled()) {
            return $payment;
        }

        if (! $payment->status->isOpen()) {
            throw ValidationException::withMessages([
                'payment' => 'Pembayaran ini sudah ditutup dan tidak bisa disetujui.',
            ]);
        }

        $settled = DB::transaction(function () use ($payment, $admin, $note) {
            // Re-read under a lock so two admins clicking at once cannot both
            // create a subscription for the same payment.
            $locked = PlanPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isSettled()) {
                return $locked;
            }

            /** @var Subscription $subscription */
            $subscription = $this->plans->subscribe(
                $locked->user,
                $locked->plan,
                $locked->billing_interval,
                $locked->provider,
                $locked->reference,
            );

            $locked->update([
                'status' => PlanPaymentStatus::Paid,
                'claimable_amount' => null, // frees the amount for the next payer
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
                'review_note' => $note,
                'subscription_id' => $subscription->id,
            ]);

            $this->marketplaceLedger->recordSubscription($locked->fresh());

            return $locked->fresh();
        });

        $this->notifyActivated($settled);

        return $settled;
    }

    public function reject(PlanPayment $payment, User $admin, string $reason): PlanPayment
    {
        if (! $payment->status->isOpen()) {
            throw ValidationException::withMessages([
                'payment' => 'Pembayaran ini sudah ditutup.',
            ]);
        }

        $payment->update([
            'status' => PlanPaymentStatus::Rejected,
            'claimable_amount' => null,
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
            'review_note' => $reason,
        ]);

        return $payment->fresh();
    }

    /** The payer walks away; releases the amount immediately. */
    public function cancel(PlanPayment $payment): void
    {
        if (! $payment->status->isOpen()) {
            return;
        }

        $payment->update([
            'status' => PlanPaymentStatus::Expired,
            'claimable_amount' => null,
        ]);
    }

    /** Reconcile an iPaymu charge when the webhook is delayed. */
    public function syncStatus(PlanPayment $payment): PlanPayment
    {
        if ($payment->provider !== 'ipaymu' || blank($payment->gateway_transaction_id)) {
            return $this->expireIfLapsed($payment);
        }

        $provider = $this->ipaymu();
        $result = $provider->checkTransaction(
            transactionId: $payment->gateway_transaction_id,
            reference: $payment->reference,
            currentStatus: $this->paymentStatus($payment->status),
            amount: (float) $payment->amount,
            fee: (float) $payment->gateway_fee,
            expiresAt: $payment->expires_at,
            paidAt: $payment->paid_at,
            logContext: ['plan_payment_id' => $payment->id],
        );

        return $this->applyGatewayResult($result, 'ipaymu') ?? $payment->fresh();
    }

    /** Applies a verified callback or status response to a plan payment. */
    public function applyGatewayResult(PaymentResult $result, string $providerKey): ?PlanPayment
    {
        if (blank($result->reference)) {
            return null;
        }

        $payment = PlanPayment::where('provider', $providerKey)
            ->where('reference', $result->reference)
            ->first();

        if (! $payment) {
            return null;
        }

        if ($result->amount !== null && ! Money::equals((float) $payment->amount, $result->amount)) {
            throw new \RuntimeException(sprintf(
                'Nominal callback langganan (%s) tidak cocok dengan tagihan (%s).',
                Money::format($result->amount),
                Money::format((float) $payment->amount),
            ));
        }

        $payment->forceFill([
            'gateway_fee' => $result->fee ?? $payment->gateway_fee,
            'gateway_response' => $result->raw !== [] ? $result->raw : $payment->gateway_response,
            'gateway_error' => $result->error,
            'expires_at' => $result->expiresAt ?? $payment->expires_at,
        ])->save();

        return match ($result->status) {
            PaymentStatus::Paid => $this->settleIpaymu($payment, $result),
            PaymentStatus::Expired => $this->closeGatewayPayment($payment, PlanPaymentStatus::Expired),
            PaymentStatus::Failed => $this->closeGatewayPayment($payment, PlanPaymentStatus::Failed),
            default => $payment->fresh(),
        };
    }

    /**
     * Frees amounts whose window has closed. Safe to run repeatedly.
     *
     * Payments already confirmed by the payer are deliberately left alone: the
     * money may genuinely be on its way, and expiring them would hide a real
     * transfer from the admin queue.
     */
    public function expireLapsed(): int
    {
        return PlanPayment::where('status', PlanPaymentStatus::Pending->value)
            ->where('expires_at', '<', now())
            ->update([
                'status' => PlanPaymentStatus::Expired->value,
                'claimable_amount' => null,
            ]);
    }

    public function expireIfLapsed(PlanPayment $payment): PlanPayment
    {
        if ($payment->status === PlanPaymentStatus::Pending && $payment->expires_at->isPast()) {
            $payment->update([
                'status' => PlanPaymentStatus::Expired,
                'claimable_amount' => null,
            ]);
        }

        return $payment;
    }

    /** The payment a user should currently be looking at, if any. */
    public function openPaymentFor(User $user): ?PlanPayment
    {
        $payment = PlanPayment::with('plan')
            ->where('user_id', $user->id)
            ->open()
            ->latest('id')
            ->first();

        return $payment ? $this->expireIfLapsed($payment) : null;
    }

    private function releaseOpenPaymentsFor(User $user): void
    {
        PlanPayment::where('user_id', $user->id)
            ->open()
            ->update([
                'status' => PlanPaymentStatus::Expired->value,
                'claimable_amount' => null,
            ]);
    }

    private function openIpaymu(User $user, Plan $plan, string $interval, int $base): PlanPayment
    {
        $existing = $this->openPaymentFor($user);

        if ($existing) {
            if ($existing->plan_id === $plan->id && $existing->billing_interval === $interval) {
                return $existing;
            }

            throw ValidationException::withMessages([
                'plan' => 'Masih ada pembayaran paket yang aktif. Selesaikan atau batalkan pembayaran itu terlebih dahulu.',
            ]);
        }

        $user->loadMissing('store');
        $phone = trim((string) ($user->phone ?: $user->store?->whatsapp));

        if ($phone === '') {
            throw ValidationException::withMessages([
                'plan' => 'Tambahkan nomor WhatsApp di profil toko terlebih dahulu untuk membayar lewat iPaymu.',
            ]);
        }

        $payment = PlanPayment::create([
            'reference' => PlanPayment::generateReference(),
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_interval' => $interval,
            'provider' => 'ipaymu',
            'method' => 'qris',
            'channel' => 'mpm',
            'base_amount' => $base,
            'unique_suffix' => 0,
            'amount' => $base,
            'claimable_amount' => null,
            'status' => PlanPaymentStatus::Pending,
            'qris_payload' => '',
            'expires_at' => now()->addMinutes($this->minutesToPay()),
        ]);

        $payload = [
            'name' => $user->name,
            'phone' => $phone,
            'email' => $user->email,
            'amount' => $base,
            'notifyUrl' => route('webhooks.payments', ['provider' => 'ipaymu']),
            'referenceId' => $payment->reference,
            'paymentMethod' => $payment->method,
            'paymentChannel' => $payment->channel,
            'comments' => "Langganan paket {$plan->name} ({$interval})",
            'feeDirection' => strtoupper((string) config('payments.providers.ipaymu.fee_direction')) === 'BUYER'
                ? 'BUYER'
                : 'MERCHANT',
            'successUrl' => route('creator.subscription.pay', $payment->reference),
            'cancelUrl' => route('creator.subscription'),
        ];

        $result = $this->ipaymu()->createDirectPayment(
            payload: $payload,
            method: $payment->method,
            channel: $payment->channel,
            reference: $payment->reference,
            amount: (float) $payment->amount,
            logContext: ['plan_payment_id' => $payment->id],
        );

        $payment->forceFill([
            'status' => $result->status === PaymentStatus::Failed
                ? PlanPaymentStatus::Failed
                : PlanPaymentStatus::Pending,
            'gateway_transaction_id' => $this->ipaymu()->transactionIdFromResponse($result->raw),
            'gateway_fee' => $result->fee ?? 0,
            'instructions' => $result->instructions,
            'qris_payload' => (string) ($result->instructions['payload'] ?? ''),
            'redirect_url' => $result->redirectUrl,
            'gateway_response' => $result->raw,
            'gateway_error' => $result->error,
            'expires_at' => $result->expiresAt ?? $payment->expires_at,
        ])->save();

        return $result->isPaid()
            ? $this->settleIpaymu($payment, $result)
            : $payment->fresh();
    }

    private function settleIpaymu(PlanPayment $payment, PaymentResult $result): PlanPayment
    {
        $settled = DB::transaction(function () use ($payment, $result) {
            $locked = PlanPayment::with(['user', 'plan'])
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status->isSettled()) {
                return $locked;
            }

            $subscription = $this->plans->subscribe(
                $locked->user,
                $locked->plan,
                $locked->billing_interval,
                'ipaymu',
                $locked->gateway_transaction_id ?: $locked->reference,
            );

            SubscriptionInvoice::create([
                'subscription_id' => $subscription->id,
                'number' => SubscriptionInvoice::generateNumber(),
                'amount' => $locked->base_amount,
                'status' => 'PAID',
                'period_start' => $subscription->current_period_start,
                'period_end' => $subscription->current_period_end,
                'paid_at' => $result->paidAt ?? now(),
            ]);

            $locked->forceFill([
                'status' => PlanPaymentStatus::Paid,
                'claimable_amount' => null,
                'paid_at' => $result->paidAt ?? now(),
                'gateway_fee' => $result->fee ?? $locked->gateway_fee,
                'subscription_id' => $subscription->id,
                'gateway_error' => null,
            ])->save();

            $this->marketplaceLedger->recordSubscription($locked->fresh());

            return $locked->fresh();
        });

        $this->notifyActivated($settled);

        return $settled;
    }

    private function notifyActivated(PlanPayment $payment): void
    {
        $payment->loadMissing(['user', 'plan']);
        $this->notifications->sendOnce($payment->user, [
            'type' => 'subscription.activated',
            'category' => 'subscription',
            'priority' => 'high',
            'title' => 'Paket '.$payment->plan->name.' sudah aktif',
            'message' => 'Pembayaran '.$payment->reference.' berhasil diverifikasi dan limit akunmu sudah diperbarui.',
            'url' => route('creator.subscription'),
            'action_label' => 'Lihat paket',
            'group_key' => 'subscription:payment:'.$payment->id,
            'tone' => 'success',
            'meta' => ['plan_payment_id' => $payment->id, 'plan_id' => $payment->plan_id],
        ], 720);
    }

    private function closeGatewayPayment(PlanPayment $payment, PlanPaymentStatus $status): PlanPayment
    {
        if ($payment->status->isSettled()) {
            return $payment;
        }

        $payment->forceFill([
            'status' => $status,
            'claimable_amount' => null,
        ])->save();

        return $payment->fresh();
    }

    private function paymentStatus(PlanPaymentStatus $status): PaymentStatus
    {
        return match ($status) {
            PlanPaymentStatus::Paid => PaymentStatus::Paid,
            PlanPaymentStatus::Expired => PaymentStatus::Expired,
            PlanPaymentStatus::Rejected, PlanPaymentStatus::Failed => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }

    private function ipaymu(): IpaymuProvider
    {
        $provider = $this->manager->driver('ipaymu');

        if (! $provider instanceof IpaymuProvider) {
            throw new \RuntimeException('Driver iPaymu tidak tersedia.');
        }

        return $provider;
    }

    /**
     * Claims an unused amount.
     *
     * Suffixes are tried in random order rather than counting up, so amounts do
     * not leak how many people have subscribed. The unique index is the real
     * arbiter — a collision surfaces as a constraint violation and we simply try
     * the next candidate.
     */
    private function allocate(User $user, Plan $plan, string $interval, int $base): PlanPayment
    {
        $candidates = range(self::SUFFIX_MIN, self::SUFFIX_MAX);
        shuffle($candidates);

        foreach (array_slice($candidates, 0, self::MAX_ATTEMPTS) as $suffix) {
            $amount = $base + $suffix;

            try {
                return PlanPayment::create([
                    'reference' => PlanPayment::generateReference(),
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'billing_interval' => $interval,
                    'base_amount' => $base,
                    'unique_suffix' => $suffix,
                    'amount' => $amount,
                    'claimable_amount' => $amount,
                    'status' => PlanPaymentStatus::Pending,
                    'qris_payload' => Qris::dynamic($this->staticPayload(), $amount),
                    'expires_at' => now()->addMinutes($this->minutesToPay()),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Someone else holds this amount; try another suffix.
                continue;
            }
        }

        throw ValidationException::withMessages([
            'plan' => 'Sistem pembayaran sedang penuh. Coba lagi beberapa menit lagi ya.',
        ]);
    }

    private function staticPayload(): ?string
    {
        $payload = trim((string) config('payments.qris.static_payload'));

        if ($payload === '' || ! Qris::looksValid($payload)) {
            return null;
        }

        return $payload;
    }
}
