<?php

namespace App\Payments\Providers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Payments\PaymentProviderInterface;
use App\Payments\PaymentResult;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Bank transfer settled by hand. There is no gateway: a finance admin confirms
 * the incoming transfer, which flows through the same PaymentService path as a
 * gateway callback, so the ledger treats it identically.
 */
class ManualTransferProvider implements PaymentProviderInterface
{
    public function key(): string
    {
        return 'manual_transfer';
    }

    public function displayName(): string
    {
        return 'Transfer Bank Manual';
    }

    public function supportedMethods(): array
    {
        return [[
            'method' => 'bank_transfer',
            'channel' => 'manual',
            'label' => 'Transfer Bank (dicek manual)',
            'fee_percent' => 0,
            'fee_fixed' => 0,
        ]];
    }

    public function createPayment(Payment $payment, array $options = []): PaymentResult
    {
        $accounts = PlatformSetting::get('payments.manual_accounts', []);

        // A small unique suffix on the amount lets finance match the transfer
        // to the order without asking the buyer for a reference number.
        $uniqueCode = random_int(101, 999);

        return new PaymentResult(
            status: PaymentStatus::Pending,
            reference: 'MT-'.Str::upper(Str::random(10)),
            amount: (float) $payment->amount,
            fee: 0.0,
            instructions: [
                'type' => 'manual',
                'accounts' => $accounts,
                'unique_code' => $uniqueCode,
                'steps' => [
                    'Transfer ke salah satu rekening di atas.',
                    'Nominal harus sama persis sampai 3 digit terakhir.',
                    'Pembayaran dicek maksimal 1x24 jam kerja.',
                ],
            ],
            expiresAt: now()->addHours(24),
        );
    }

    public function checkStatus(Payment $payment): PaymentResult
    {
        return new PaymentResult(status: $payment->status, reference: $payment->reference);
    }

    public function verifyWebhook(Request $request): bool
    {
        // Manual transfers are never confirmed over a public webhook.
        return false;
    }

    public function parseWebhook(Request $request): PaymentResult
    {
        return new PaymentResult(status: PaymentStatus::Pending);
    }

    public function expire(Payment $payment): PaymentResult
    {
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
            error: 'Refund transfer manual diproses di luar sistem oleh tim finance.',
        );
    }
}
