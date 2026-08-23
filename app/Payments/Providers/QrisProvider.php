<?php

namespace App\Payments\Providers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\PaymentProviderInterface;
use App\Payments\PaymentResult;
use App\Support\Qris;
use App\Support\QrImage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * QRIS settled by hand.
 *
 * The merchant's static QRIS is turned into a single-use dynamic code with the
 * amount locked in, so the payer's wallet fills the figure in and cannot change
 * it. There is no callback: an admin reads the incoming amount off the wallet
 * and confirms, which then runs through the same PaymentService path a gateway
 * callback would — the ledger cannot tell the difference.
 *
 * Because the amount is the only identifier, it carries a per-order suffix and
 * the database refuses to let two open payments share one.
 */
class QrisProvider implements PaymentProviderInterface
{
    private const SUFFIX_MIN = 1;

    private const SUFFIX_MAX = 999;

    private const MAX_ATTEMPTS = 40;

    public function key(): string
    {
        return 'qris';
    }

    public function displayName(): string
    {
        return 'QRIS';
    }

    public function supportedMethods(): array
    {
        return [[
            'method' => 'qris',
            'channel' => 'static',
            'label' => 'QRIS (semua e-wallet & m-banking)',
            'fee_percent' => (float) config('payments.qris.fee_percent', 0),
            'fee_fixed' => (float) config('payments.qris.fee_fixed', 0),
        ]];
    }

    public function createPayment(Payment $payment, array $options = []): PaymentResult
    {
        $static = $this->staticPayload();

        if ($static === null) {
            throw ValidationException::withMessages([
                'method' => 'Pembayaran QRIS belum aktif. Pilih metode lain dulu ya.',
            ]);
        }

        $base = (int) round((float) $payment->amount);

        // Releases anything this order was holding, so retrying a checkout does
        // not leave dead amounts reserved.
        $this->releaseSiblings($payment);

        [$amount, $suffix] = $this->claimAmount($payment, $base);

        $qris = Qris::dynamic($static, $amount);
        $minutes = (int) config('payments.qris.window_minutes', 30);

        return new PaymentResult(
            status: PaymentStatus::Pending,
            reference: 'QR-'.Str::upper(Str::random(10)),
            amount: (float) $amount,
            fee: (float) $payment->fee,
            instructions: [
                'type' => 'qris',
                'merchant' => Qris::merchantName($static),
                'base_amount' => $base,
                'unique_suffix' => $suffix,
                'amount' => $amount,
                'qr_svg' => QrImage::svgDataUri($qris),
                'steps' => [
                    'Buka DANA, GoPay, OVO, ShopeePay, atau m-banking apa pun.',
                    'Scan QR di atas — nominalnya sudah terkunci.',
                    'Bayar persis sejumlah itu, jangan dibulatkan.',
                    'Pesanan diproses setelah admin mencocokkan dana masuk.',
                ],
            ],
            expiresAt: now()->addMinutes($minutes),
        );
    }

    public function checkStatus(Payment $payment): PaymentResult
    {
        // Nothing to poll: the wallet never tells us anything.
        return new PaymentResult(status: $payment->status, reference: $payment->reference);
    }

    public function verifyWebhook(Request $request): bool
    {
        // A manual QRIS payment is never confirmed over a public endpoint.
        return false;
    }

    public function parseWebhook(Request $request): PaymentResult
    {
        return new PaymentResult(status: PaymentStatus::Pending);
    }

    public function expire(Payment $payment): PaymentResult
    {
        $payment->forceFill(['claimable_amount' => null])->save();

        return new PaymentResult(status: PaymentStatus::Expired, reference: $payment->reference);
    }

    public function supportsRefund(): bool
    {
        return false;
    }

    public function refund(Payment $payment, float $amount, ?string $reason = null): PaymentResult
    {
        return new PaymentResult(
            status: $payment->status,
            error: 'Refund QRIS dikirim manual oleh tim finance ke rekening pembeli.',
        );
    }

    /**
     * Claims an unused payable amount for this payment.
     *
     * Suffixes are tried in random order so the figures do not leak order
     * volume. The unique index is the real arbiter: a clash surfaces as a
     * constraint violation and the next candidate is tried.
     *
     * @return array{0: int, 1: int} the payable amount and its suffix
     */
    private function claimAmount(Payment $payment, int $base): array
    {
        $candidates = range(self::SUFFIX_MIN, self::SUFFIX_MAX);
        shuffle($candidates);

        foreach (array_slice($candidates, 0, self::MAX_ATTEMPTS) as $suffix) {
            $amount = $base + $suffix;

            try {
                $payment->forceFill([
                    'unique_suffix' => $suffix,
                    'claimable_amount' => $amount,
                    'amount' => $amount,
                ])->save();

                return [$amount, $suffix];
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw ValidationException::withMessages([
            'method' => 'Antrean pembayaran QRIS sedang penuh. Coba lagi sebentar lagi ya.',
        ]);
    }

    /** Frees amounts held by this order's other open QRIS attempts. */
    private function releaseSiblings(Payment $payment): void
    {
        Payment::where('order_id', $payment->order_id)
            ->where('provider', $this->key())
            ->whereKeyNot($payment->id)
            ->whereNotNull('claimable_amount')
            ->update(['claimable_amount' => null]);
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
