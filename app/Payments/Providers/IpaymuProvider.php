<?php

namespace App\Payments\Providers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\PaymentProviderInterface;
use App\Payments\PaymentResult;
use App\Support\QrImage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * iPaymu API v2 adapter.
 *
 * Direct Payment keeps the payment choice inside JualanYok while iPaymu owns
 * the actual QR/VA/e-wallet screen. Callback signatures use the merchant VA
 * (not the API key), exactly as required by iPaymu's callback contract.
 */
class IpaymuProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly string $va,
        private readonly string $apiKey,
        private readonly bool $production = false,
        private readonly string $feeDirection = 'MERCHANT',
    ) {}

    public function key(): string
    {
        return 'ipaymu';
    }

    public function displayName(): string
    {
        return 'iPaymu';
    }

    public function supportedMethods(): array
    {
        // Fees are absorbed by the platform. This makes the amount sent to
        // iPaymu and returned by its callback identical to the order total.
        return [
            ['method' => 'qris', 'channel' => 'mpm', 'label' => 'QRIS Otomatis', 'fee_percent' => 0, 'fee_fixed' => 0],
            ['method' => 'va', 'channel' => 'bca', 'label' => 'Virtual Account BCA', 'fee_percent' => 0, 'fee_fixed' => 0],
            ['method' => 'va', 'channel' => 'bni', 'label' => 'Virtual Account BNI', 'fee_percent' => 0, 'fee_fixed' => 0],
            ['method' => 'va', 'channel' => 'bri', 'label' => 'Virtual Account BRI', 'fee_percent' => 0, 'fee_fixed' => 0],
            ['method' => 'va', 'channel' => 'mandiri', 'label' => 'Virtual Account Mandiri', 'fee_percent' => 0, 'fee_fixed' => 0],
            ['method' => 'va', 'channel' => 'permata', 'label' => 'Virtual Account Permata', 'fee_percent' => 0, 'fee_fixed' => 0],
            ['method' => 'ewallet', 'channel' => 'dana', 'label' => 'DANA', 'fee_percent' => 0, 'fee_fixed' => 0],
            ['method' => 'ewallet', 'channel' => 'shopeepay', 'label' => 'ShopeePay', 'fee_percent' => 0, 'fee_fixed' => 0],
        ];
    }

    private function baseUrl(): string
    {
        return $this->production
            ? 'https://my.ipaymu.com'
            : 'https://sandbox.ipaymu.com';
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()->timeout(25);
    }

    public function createPayment(Payment $payment, array $options = []): PaymentResult
    {
        $payment->loadMissing('order.items');
        $order = $payment->order;

        if (blank($this->va) || blank($this->apiKey)) {
            return new PaymentResult(
                status: PaymentStatus::Failed,
                error: 'Kredensial iPaymu belum dikonfigurasi.',
            );
        }

        if (blank($order->customer_phone)) {
            return new PaymentResult(
                status: PaymentStatus::Failed,
                error: 'Nomor WhatsApp diperlukan untuk pembayaran iPaymu.',
            );
        }

        $reference = $order->number.'-'.$payment->id;
        $payload = [
            'name' => $order->customer_name,
            'phone' => $order->customer_phone,
            'email' => $order->customer_email,
            'amount' => (int) round((float) $payment->amount),
            'notifyUrl' => route('webhooks.payments', ['provider' => $this->key()]),
            'referenceId' => $reference,
            'paymentMethod' => $payment->method,
            'paymentChannel' => $payment->channel,
            'comments' => 'Pembayaran pesanan '.$order->number,
            'feeDirection' => strtoupper($this->feeDirection) === 'BUYER' ? 'BUYER' : 'MERCHANT',
            'successUrl' => route('checkout.status', $order->number),
            'cancelUrl' => route('checkout.show', $order->number),
        ];

        // iPaymu owns the fixed expiry for QRIS (5 minutes) and BCA VA
        // (12 hours). Sending a custom expiry for either can be rejected.
        if (! ($payment->method === 'qris' || ($payment->method === 'va' && $payment->channel === 'bca'))) {
            $payload['expired'] = $this->expiryHours($payment->method, $payment->channel);
            $payload['expiredType'] = 'hours';
        }

        $rawResponse = [];

        try {
            // iPaymu calculates its signature from JSON_UNESCAPED_SLASHES.
            // This is especially important here because the payload contains
            // notify/success/cancel URLs: `\/` and `/` produce different hashes.
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $timestamp = now('Asia/Jakarta')->format('YmdHis');
            $signature = $this->requestSignature('POST', $body);

            $response = $this->client()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'va' => trim($this->va),
                    'signature' => $signature,
                    'timestamp' => $timestamp,
                ])
                ->send('POST', $this->baseUrl().'/api/v2/payment/direct', ['body' => $body])
                ->throw()
                ->json();
            $rawResponse = is_array($response) ? $response : [];

            if ((int) ($response['Status'] ?? 0) !== 200 || ($response['Success'] ?? false) !== true) {
                throw new RuntimeException((string) ($response['Message'] ?? 'iPaymu menolak pembuatan pembayaran.'));
            }

            $data = $this->responseData($response['Data'] ?? []);
            $redirectUrl = $this->stringValue($data, [
                'Url', 'url', 'RedirectUrl', 'redirectUrl', 'PaymentUrl', 'paymentUrl',
            ]);
            $instructions = $this->instructions($payment, $data);

            if (! $this->hasPayableInstructions($payment, $instructions, $redirectUrl)) {
                throw new RuntimeException(
                    'Respons iPaymu tidak berisi QR, nomor pembayaran, atau tautan pembayaran.'
                );
            }

            return new PaymentResult(
                status: PaymentStatus::Pending,
                reference: $this->stringValue($data, ['ReferenceId', 'referenceId', 'reference_id']) ?? $reference,
                amount: (float) ($this->value($data, ['Total', 'total']) ?? $payment->amount),
                fee: ($fee = $this->value($data, ['Fee', 'fee'])) !== null ? (float) $fee : null,
                instructions: $instructions,
                redirectUrl: $redirectUrl,
                expiresAt: $this->parseDate($this->value($data, ['Expired', 'expired', 'ExpiredAt', 'expired_at']))
                    ?? $this->fallbackExpiry($payment->method, $payment->channel),
                raw: $response,
            );
        } catch (Throwable $e) {
            if ($rawResponse === [] && $e instanceof RequestException) {
                $errorResponse = $e->response->json();
                $rawResponse = is_array($errorResponse) ? $errorResponse : [];
            }

            Log::error('ipaymu.create_failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return new PaymentResult(
                status: PaymentStatus::Failed,
                error: $this->publicError($e),
                raw: $rawResponse,
            );
        }
    }

    public function checkStatus(Payment $payment): PaymentResult
    {
        $transactionId = $this->transactionId($payment);

        if (blank($transactionId)) {
            return new PaymentResult(
                status: $payment->status,
                reference: $payment->reference,
                amount: (float) $payment->amount,
                fee: (float) $payment->fee,
                expiresAt: $payment->expires_at,
                paidAt: $payment->paid_at,
                error: 'ID transaksi iPaymu belum tersimpan. Menunggu callback pembayaran.',
            );
        }

        $rawResponse = [];

        try {
            $payload = ['transactionId' => is_numeric($transactionId) ? (int) $transactionId : $transactionId];
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $timestamp = now('Asia/Jakarta')->format('YmdHis');

            $response = $this->client()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'va' => trim($this->va),
                    'signature' => $this->requestSignature('POST', $body),
                    'timestamp' => $timestamp,
                ])
                ->send('POST', $this->baseUrl().'/api/v2/transaction', ['body' => $body])
                ->throw()
                ->json();
            $rawResponse = is_array($response) ? $response : [];

            if ((int) ($rawResponse['Status'] ?? 0) !== 200 || ($rawResponse['Success'] ?? false) !== true) {
                throw new RuntimeException((string) ($rawResponse['Message'] ?? 'Status transaksi tidak dapat diperiksa.'));
            }

            $data = $this->responseData($rawResponse['Data'] ?? []);
            $status = $this->transactionStatus($data);

            return new PaymentResult(
                status: $status,
                // The local reference is the signed merchant reference used to
                // locate this payment. The numeric iPaymu ID is provider-owned.
                reference: $payment->reference,
                amount: ($amount = $this->value($data, ['Amount', 'amount', 'Total', 'total', 'SubTotal', 'subTotal', 'sub_total'])) !== null
                    ? (float) $amount
                    : (float) $payment->amount,
                fee: ($fee = $this->value($data, ['Fee', 'fee'])) !== null ? (float) $fee : (float) $payment->fee,
                expiresAt: $this->parseDate($this->value($data, ['ExpiredDate', 'expiredDate', 'Expired', 'expired', 'expired_at']))
                    ?? $payment->expires_at,
                paidAt: $status === PaymentStatus::Paid
                    ? ($this->parseDate($this->value($data, ['SuccessDate', 'successDate', 'PaidAt', 'paidAt', 'paid_at'])) ?? now())
                    : $payment->paid_at,
                eventId: 'status-'.$transactionId.'-'.$status->value,
                raw: $rawResponse,
            );
        } catch (Throwable $e) {
            if ($rawResponse === [] && $e instanceof RequestException) {
                $errorResponse = $e->response->json();
                $rawResponse = is_array($errorResponse) ? $errorResponse : [];
            }

            Log::warning('ipaymu.status_check_failed', [
                'payment_id' => $payment->id,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return new PaymentResult(
                status: $payment->status,
                reference: $payment->reference,
                amount: (float) $payment->amount,
                fee: (float) $payment->fee,
                expiresAt: $payment->expires_at,
                paidAt: $payment->paid_at,
                error: 'Status iPaymu belum dapat diperiksa. Coba lagi beberapa saat.',
                raw: $rawResponse,
            );
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        $received = trim((string) $request->header('X-Signature'));

        if ($received === '' || $this->va === '') {
            return false;
        }

        $payload = $this->normaliseCallback($request->all());

        if (isset($payload['merchant']) && (string) $payload['merchant'] !== $this->va) {
            return false;
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $expected = hash_hmac('sha256', $json, $this->va);

        return hash_equals($expected, $received);
    }

    public function parseWebhook(Request $request): PaymentResult
    {
        $data = $request->all();
        $statusCode = (int) ($data['status_code'] ?? 0);
        $transactionStatus = (int) ($data['transaction_status_code'] ?? 0);
        $textStatus = strtolower((string) ($data['status'] ?? 'pending'));

        $status = match (true) {
            $statusCode === 1,
            in_array($transactionStatus, [1, 6], true),
            $textStatus === 'berhasil' => PaymentStatus::Paid,
            $statusCode === -2,
            $textStatus === 'expired' => PaymentStatus::Expired,
            in_array($textStatus, ['gagal', 'failed', 'cancelled', 'canceled'], true) => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        $reference = $data['reference_id'] ?? $data['referenceId'] ?? null;
        $eventId = $request->header('X-External-ID')
            ?: ($data['trx_id'] ?? null)
            ?: hash('sha256', json_encode($this->normaliseCallback($data), JSON_THROW_ON_ERROR));

        return new PaymentResult(
            status: $status,
            reference: $reference !== null ? (string) $reference : null,
            amount: isset($data['amount']) ? (float) $data['amount'] : (isset($data['total']) ? (float) $data['total'] : null),
            fee: isset($data['fee']) ? (float) $data['fee'] : null,
            paidAt: $status === PaymentStatus::Paid ? ($this->parseDate($data['paid_at'] ?? null) ?? now()) : null,
            eventId: (string) $eventId,
            raw: $data,
        );
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
            reference: $payment->reference,
            error: 'Refund iPaymu diproses dari dashboard merchant oleh tim finance.',
        );
    }

    private function requestSignature(string $method, string $body): string
    {
        $bodyHash = strtolower(hash('sha256', $body));
        $va = trim($this->va);
        $apiKey = trim($this->apiKey);
        $stringToSign = strtoupper($method).':'.$va.':'.$bodyHash.':'.$apiKey;

        return hash_hmac('sha256', $stringToSign, $apiKey);
    }

    private function publicError(Throwable $error): string
    {
        $message = $error instanceof RequestException
            ? (string) ($error->response->json('Message') ?: $error->response->json('message'))
            : $error->getMessage();

        $normalisedMessage = strtolower($message);

        if (str_contains($normalisedMessage, 'unauthorized signature')) {
            return 'Autentikasi iPaymu ditolak. Periksa kembali pasangan VA dan API Key Live.';
        }

        if (str_contains($normalisedMessage, 'suspicious buyer')) {
            return 'Pembayaran belum dapat diproses oleh pemeriksaan keamanan penyedia. Periksa kembali data pembeli, tunggu beberapa saat, atau gunakan metode pembayaran lain.';
        }

        return filled($message)
            ? 'iPaymu menolak pembayaran: '.str($message)->squish()->limit(160)
            : 'Tagihan belum berhasil dibuat oleh iPaymu. Silakan pilih ulang metode pembayaran.';
    }

    private function normaliseCallback(array $data): array
    {
        unset($data['signature']);

        $normalised = [];

        foreach ($data as $key => $value) {
            $normalised[$key] = match (true) {
                $key === 'is_escrow' => in_array($value, [true, 1, '1', 'true'], true),
                in_array($key, ['trx_id', 'status_code', 'transaction_status_code', 'paid_off'], true) => (int) $value,
                $key === 'additional_info' && $value === '[]' => [],
                $key === 'additional_info' => $value,
                $value === null => 'null',
                $value === true => 'true',
                $value === false => 'false',
                default => (string) $value,
            };
        }

        $normalised['additional_info'] ??= [];
        ksort($normalised, SORT_STRING);

        return $normalised;
    }

    private function transactionId(Payment $payment): ?string
    {
        $response = $payment->attempts()
            ->where('action', 'create')
            ->latest('id')
            ->value('response');

        if (! is_array($response)) {
            return null;
        }

        $data = $this->responseData(data_get($response, 'raw.Data', []));

        return $this->stringValue($data, ['TransactionId', 'transactionId', 'transaction_id', 'trx_id']);
    }

    private function transactionStatus(array $data): PaymentStatus
    {
        $code = (int) ($this->value($data, [
            'Status', 'status', 'StatusCode', 'statusCode', 'status_code',
            'TransactionStatusCode', 'transactionStatusCode', 'transaction_status_code',
        ]) ?? 0);
        $text = strtolower(trim((string) ($this->value($data, [
            'PaidStatus', 'paidStatus', 'paid_status', 'StatusDesc', 'statusDesc', 'status_desc',
        ]) ?? '')));

        return match (true) {
            in_array($code, [1, 6], true),
            in_array($text, ['paid', 'success', 'successful', 'berhasil', 'completed', 'settled'], true) => PaymentStatus::Paid,
            $code === -2,
            in_array($text, ['expired', 'kedaluwarsa'], true) => PaymentStatus::Expired,
            $code < 0,
            in_array($text, ['failed', 'gagal', 'cancelled', 'canceled'], true) => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }

    private function instructions(Payment $payment, array $data): array
    {
        if ($payment->method === 'va') {
            return [
                'type' => 'va',
                'bank' => strtoupper((string) ($this->stringValue($data, ['Channel', 'channel']) ?? $payment->channel)),
                'va_number' => $this->stringValue($data, ['PaymentNo', 'paymentNo', 'payment_no', 'Va', 'va']) ?? '',
                'payment_name' => $this->stringValue($data, ['PaymentName', 'paymentName', 'payment_name']),
                'note' => $this->stringValue($data, ['Note', 'note']),
                'steps' => [
                    'Salin nomor Virtual Account di atas.',
                    'Bayar lewat aplikasi bank, ATM, atau kanal yang tersedia.',
                    'Status pesanan diperbarui otomatis setelah pembayaran diterima.',
                ],
            ];
        }

        if ($payment->method === 'qris') {
            $payload = $this->stringValue($data, [
                'QrString', 'QRString', 'qrString', 'qr_string',
                'QrCode', 'QRCode', 'qrCode', 'qr_code',
                'PaymentNo', 'paymentNo', 'payment_no',
            ]);

            return [
                'type' => filled($payload) ? 'qris' : 'redirect',
                'payload' => $payload,
                'qr_svg' => filled($payload) ? QrImage::svgDataUri($payload) : null,
                'payment_name' => $this->stringValue($data, ['PaymentName', 'paymentName', 'payment_name']),
                'note' => $this->stringValue($data, ['Note', 'note']),
                'steps' => [
                    filled($payload)
                        ? 'Scan QRIS dengan aplikasi bank atau e-wallet.'
                        : 'Tekan tombol Lanjut ke Halaman Pembayaran.',
                    'Pastikan nominal pembayaran sesuai dengan total tagihan.',
                    'Kembali ke halaman ini; status diperbarui otomatis.',
                ],
            ];
        }

        return [
            'type' => 'redirect',
            'payment_name' => $this->stringValue($data, ['PaymentName', 'paymentName', 'payment_name']),
            'payment_no' => $this->stringValue($data, ['PaymentNo', 'paymentNo', 'payment_no']),
            'note' => $this->stringValue($data, ['Note', 'note']),
            'steps' => [
                'Tekan tombol Lanjut ke Halaman Pembayaran.',
                $payment->method === 'qris'
                    ? 'Scan QRIS yang ditampilkan iPaymu dengan aplikasi bank atau e-wallet.'
                    : 'Selesaikan pembayaran di halaman aman iPaymu.',
                'Kembali ke halaman ini; status diperbarui otomatis.',
            ],
        ];
    }

    private function responseData(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            return isset($data[0]) && is_array($data[0]) ? $data[0] : [];
        }

        return $data;
    }

    private function value(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return null;
    }

    private function stringValue(array $data, array $keys): ?string
    {
        $value = $this->value($data, $keys);

        return is_scalar($value) && filled((string) $value) ? (string) $value : null;
    }

    private function hasPayableInstructions(Payment $payment, array $instructions, ?string $redirectUrl): bool
    {
        return match ($payment->method) {
            'va', 'cstore' => filled($instructions['va_number'] ?? $instructions['payment_no'] ?? null),
            'qris' => filled($instructions['payload'] ?? null) || filled($redirectUrl),
            default => filled($redirectUrl) || filled($instructions['payment_no'] ?? null),
        };
    }

    private function expiryHours(string $method, ?string $channel): int
    {
        return match (true) {
            $method === 'qris' => 1,
            $method === 'va' && $channel === 'bri' => 2,
            $method === 'va' && $channel === 'bsi' => 3,
            $method === 'va' && $channel === 'bca' => 12,
            default => max(1, (int) config('payments.expiry_hours', 24)),
        };
    }

    private function fallbackExpiry(string $method, ?string $channel): Carbon
    {
        return $method === 'qris'
            ? now()->addMinutes(5)
            : now()->addHours($this->expiryHours($method, $channel));
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value, 'Asia/Jakarta');
        } catch (Throwable) {
            return null;
        }
    }
}
