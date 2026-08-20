<?php

namespace App\Payments\Providers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\PaymentProviderInterface;
use App\Payments\PaymentResult;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Development and test gateway. It behaves exactly like a real provider —
 * signed callbacks, expiries, idempotent event ids — but settles through a
 * local "simulate payment" endpoint instead of a bank, so the entire checkout
 * → ledger → fulfilment path is exercisable without external credentials.
 */
class MockProvider implements PaymentProviderInterface
{
    public function __construct(private readonly string $secret) {}

    public function key(): string
    {
        return 'mock';
    }

    public function displayName(): string
    {
        return 'Simulasi Pembayaran (Dev)';
    }

    public function supportedMethods(): array
    {
        return [
            ['method' => 'qris', 'channel' => 'qris', 'label' => 'QRIS', 'fee_percent' => 0.7, 'fee_fixed' => 0],
            ['method' => 'va', 'channel' => 'bca', 'label' => 'Virtual Account BCA', 'fee_percent' => 0, 'fee_fixed' => 4000],
            ['method' => 'va', 'channel' => 'mandiri', 'label' => 'Virtual Account Mandiri', 'fee_percent' => 0, 'fee_fixed' => 4000],
            ['method' => 'ewallet', 'channel' => 'gopay', 'label' => 'GoPay', 'fee_percent' => 2.0, 'fee_fixed' => 0],
            ['method' => 'ewallet', 'channel' => 'ovo', 'label' => 'OVO', 'fee_percent' => 2.0, 'fee_fixed' => 0],
        ];
    }

    public function createPayment(Payment $payment, array $options = []): PaymentResult
    {
        $reference = 'MOCK-'.Str::upper(Str::random(12));
        $expiresAt = now()->addHours((int) config('payments.expiry_hours', 24));

        $instructions = match ($payment->method) {
            'va' => [
                'type' => 'va',
                'bank' => Str::upper((string) $payment->channel),
                'va_number' => '8808'.random_int(100000000, 999999999),
                'steps' => [
                    'Buka aplikasi m-banking kamu.',
                    'Pilih menu Transfer ke Virtual Account.',
                    'Masukkan nomor Virtual Account di atas.',
                    'Pastikan nominalnya sama persis, lalu konfirmasi.',
                ],
            ],
            'qris' => [
                'type' => 'qris',
                'payload' => '00020101021126'.Str::upper(Str::random(24)),
                'steps' => [
                    'Buka aplikasi e-wallet atau m-banking.',
                    'Scan QR di layar ini.',
                    'Periksa nominal, lalu bayar.',
                ],
            ],
            'ewallet' => [
                'type' => 'ewallet',
                'wallet' => Str::upper((string) $payment->channel),
                'deeplink' => url('/pay/simulate/'.$reference),
                'steps' => ['Tekan tombol bayar, lalu selesaikan di aplikasi e-wallet.'],
            ],
            default => ['type' => 'manual', 'steps' => ['Ikuti instruksi pembayaran di halaman ini.']],
        };

        return new PaymentResult(
            status: PaymentStatus::Pending,
            reference: $reference,
            amount: (float) $payment->amount,
            fee: $this->feeFor($payment),
            instructions: $instructions,
            redirectUrl: null,
            expiresAt: $expiresAt,
            raw: ['simulated' => true],
        );
    }

    public function checkStatus(Payment $payment): PaymentResult
    {
        // The mock gateway has no remote state; the local record is the truth.
        return new PaymentResult(
            status: $payment->status,
            reference: $payment->reference,
            amount: (float) $payment->amount,
            paidAt: $payment->paid_at,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $signature = (string) $request->header('X-Jualanyok-Signature', '');

        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $this->secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): PaymentResult
    {
        $data = $request->json()->all();

        $status = match ($data['status'] ?? '') {
            'paid', 'settlement', 'capture' => PaymentStatus::Paid,
            'expired' => PaymentStatus::Expired,
            'failed', 'deny', 'cancel' => PaymentStatus::Failed,
            'refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Pending,
        };

        return new PaymentResult(
            status: $status,
            reference: $data['reference'] ?? null,
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            fee: isset($data['fee']) ? (float) $data['fee'] : null,
            paidAt: $status === PaymentStatus::Paid ? now() : null,
            eventId: $data['event_id'] ?? null,
            raw: $data,
        );
    }

    public function expire(Payment $payment): PaymentResult
    {
        return new PaymentResult(
            status: PaymentStatus::Expired,
            reference: $payment->reference,
            amount: (float) $payment->amount,
        );
    }

    public function supportsRefund(): bool
    {
        return true;
    }

    public function refund(Payment $payment, float $amount, ?string $reason = null): PaymentResult
    {
        return new PaymentResult(
            status: $amount >= (float) $payment->amount
                ? PaymentStatus::Refunded
                : PaymentStatus::PartiallyRefunded,
            reference: $payment->reference,
            amount: $amount,
            raw: ['reason' => $reason],
        );
    }

    private function feeFor(Payment $payment): float
    {
        $method = collect($this->supportedMethods())
            ->firstWhere(fn ($m) => $m['method'] === $payment->method && $m['channel'] === $payment->channel);

        if (! $method) {
            return 0.0;
        }

        return round((float) $payment->amount * $method['fee_percent'] / 100 + $method['fee_fixed'], 2);
    }
}
