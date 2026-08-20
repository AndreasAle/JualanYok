<?php

namespace App\Payments\Providers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\PaymentProviderInterface;
use App\Payments\PaymentResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Midtrans Snap adapter.
 *
 * Wired against the documented Snap + notification contract and driven purely
 * by environment credentials. It is inactive until MIDTRANS_SERVER_KEY is set;
 * without live sandbox credentials in this environment it has not been run
 * end-to-end, so treat the first production run as an integration test.
 */
class MidtransProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly string $serverKey,
        private readonly string $clientKey,
        private readonly bool $production = false,
    ) {}

    public function key(): string
    {
        return 'midtrans';
    }

    public function displayName(): string
    {
        return 'Midtrans';
    }

    public function supportedMethods(): array
    {
        return [
            ['method' => 'qris', 'channel' => 'qris', 'label' => 'QRIS', 'fee_percent' => 0.7, 'fee_fixed' => 0],
            ['method' => 'va', 'channel' => 'bca', 'label' => 'VA BCA', 'fee_percent' => 0, 'fee_fixed' => 4000],
            ['method' => 'va', 'channel' => 'bni', 'label' => 'VA BNI', 'fee_percent' => 0, 'fee_fixed' => 4000],
            ['method' => 'ewallet', 'channel' => 'gopay', 'label' => 'GoPay', 'fee_percent' => 2.0, 'fee_fixed' => 0],
            ['method' => 'card', 'channel' => 'credit_card', 'label' => 'Kartu Kredit/Debit', 'fee_percent' => 2.9, 'fee_fixed' => 2000],
        ];
    }

    private function baseUrl(): string
    {
        return $this->production
            ? 'https://app.midtrans.com/snap/v1'
            : 'https://app.sandbox.midtrans.com/snap/v1';
    }

    private function client()
    {
        return Http::withBasicAuth($this->serverKey, '')
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 200);
    }

    public function createPayment(Payment $payment, array $options = []): PaymentResult
    {
        $order = $payment->order;

        try {
            $response = $this->client()->post($this->baseUrl().'/transactions', [
                'transaction_details' => [
                    // Midtrans requires a globally unique id per attempt.
                    'order_id' => $order->number.'-'.$payment->id,
                    'gross_amount' => (int) round((float) $payment->amount),
                ],
                'customer_details' => [
                    'first_name' => $order->customer_name,
                    'email' => $order->customer_email,
                    'phone' => $order->customer_phone,
                ],
                'expiry' => [
                    'unit' => 'hour',
                    'duration' => (int) config('payments.expiry_hours', 24),
                ],
                'callbacks' => ['finish' => route('checkout.status', $order->number)],
            ])->throw()->json();

            return new PaymentResult(
                status: PaymentStatus::Pending,
                reference: $order->number.'-'.$payment->id,
                amount: (float) $payment->amount,
                instructions: ['type' => 'redirect', 'token' => $response['token'] ?? null],
                redirectUrl: $response['redirect_url'] ?? null,
                expiresAt: now()->addHours((int) config('payments.expiry_hours', 24)),
                raw: $response,
            );
        } catch (Throwable $e) {
            Log::error('midtrans.create_failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);

            return new PaymentResult(status: PaymentStatus::Failed, error: $e->getMessage());
        }
    }

    public function checkStatus(Payment $payment): PaymentResult
    {
        $host = $this->production ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';

        try {
            $data = $this->client()->get("{$host}/v2/{$payment->reference}/status")->throw()->json();

            return $this->resultFromPayload($data);
        } catch (Throwable $e) {
            return new PaymentResult(status: $payment->status, error: $e->getMessage());
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        $data = $request->json()->all();

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $field) {
            if (! isset($data[$field])) {
                return false;
            }
        }

        $expected = hash('sha512',
            $data['order_id'].$data['status_code'].$data['gross_amount'].$this->serverKey
        );

        return hash_equals($expected, (string) $data['signature_key']);
    }

    public function parseWebhook(Request $request): PaymentResult
    {
        return $this->resultFromPayload($request->json()->all());
    }

    private function resultFromPayload(array $data): PaymentResult
    {
        $status = match ($data['transaction_status'] ?? '') {
            'settlement' => PaymentStatus::Paid,
            'capture' => ($data['fraud_status'] ?? 'accept') === 'accept'
                ? PaymentStatus::Paid
                : PaymentStatus::Processing,
            'pending' => PaymentStatus::Pending,
            'expire' => PaymentStatus::Expired,
            'deny', 'cancel', 'failure' => PaymentStatus::Failed,
            'refund' => PaymentStatus::Refunded,
            'partial_refund' => PaymentStatus::PartiallyRefunded,
            default => PaymentStatus::Pending,
        };

        return new PaymentResult(
            status: $status,
            reference: $data['order_id'] ?? null,
            amount: isset($data['gross_amount']) ? (float) $data['gross_amount'] : null,
            paidAt: $status === PaymentStatus::Paid ? now() : null,
            eventId: $data['transaction_id'] ?? null,
            raw: $data,
        );
    }

    public function expire(Payment $payment): PaymentResult
    {
        $host = $this->production ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';

        try {
            $this->client()->post("{$host}/v2/{$payment->reference}/expire")->throw();
        } catch (Throwable $e) {
            Log::warning('midtrans.expire_failed', ['error' => $e->getMessage()]);
        }

        return new PaymentResult(status: PaymentStatus::Expired, reference: $payment->reference);
    }

    public function supportsRefund(): bool
    {
        return true;
    }

    public function refund(Payment $payment, float $amount, ?string $reason = null): PaymentResult
    {
        $host = $this->production ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';

        try {
            $data = $this->client()->post("{$host}/v2/{$payment->reference}/refund", [
                'refund_key' => 'rf-'.$payment->id.'-'.time(),
                'amount' => (int) round($amount),
                'reason' => $reason ?? 'Refund pembeli',
            ])->throw()->json();

            return new PaymentResult(
                status: $amount >= (float) $payment->amount
                    ? PaymentStatus::Refunded
                    : PaymentStatus::PartiallyRefunded,
                reference: $payment->reference,
                amount: $amount,
                raw: $data,
            );
        } catch (Throwable $e) {
            return new PaymentResult(status: $payment->status, error: $e->getMessage());
        }
    }
}
