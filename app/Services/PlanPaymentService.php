<?php

namespace App\Services;

use App\Enums\PlanPaymentStatus;
use App\Models\Plan;
use App\Models\PlanPayment;
use App\Models\Subscription;
use App\Models\User;
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

    public function __construct(private readonly PlanService $plans) {}

    public function enabled(): bool
    {
        return (bool) config('payments.qris.enabled') && $this->staticPayload() !== null;
    }

    public function merchantName(): ?string
    {
        $payload = $this->staticPayload();

        return $payload ? Qris::merchantName($payload) : null;
    }

    public function minutesToPay(): int
    {
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

        $this->releaseOpenPaymentsFor($user);

        return $this->allocate($user, $plan, $interval, $base);
    }

    /** The payer states they have transferred; the amount stays reserved. */
    public function confirm(PlanPayment $payment, ?string $note = null): PlanPayment
    {
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

        return DB::transaction(function () use ($payment, $admin, $note) {
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
            );

            $locked->update([
                'status' => PlanPaymentStatus::Paid,
                'claimable_amount' => null, // frees the amount for the next payer
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
                'review_note' => $note,
                'subscription_id' => $subscription->id,
            ]);

            return $locked->fresh();
        });
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
