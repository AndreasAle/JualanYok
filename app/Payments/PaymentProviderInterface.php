<?php

namespace App\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Contract every payment gateway adapter implements. Business logic (orders,
 * ledger, fulfilment) only ever talks to this interface, so swapping Midtrans
 * for Xendit — or running the mock provider in development — never touches the
 * checkout or ledger code.
 */
interface PaymentProviderInterface
{
    /** Machine name, matches the `provider` column on payments. */
    public function key(): string;

    public function displayName(): string;

    /** Payment methods this provider currently offers. */
    public function supportedMethods(): array;

    /**
     * Creates the charge at the gateway and returns the details the buyer
     * needs (VA number, QR payload, redirect URL, expiry).
     */
    public function createPayment(Payment $payment, array $options = []): PaymentResult;

    /** Fetches the authoritative status from the gateway. */
    public function checkStatus(Payment $payment): PaymentResult;

    /**
     * Verifies the callback signature. Must return false for anything that
     * cannot be cryptographically proven to come from the gateway.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Translates a verified callback body into a normalised result. The
     * amount reported here is what the ledger trusts — never the client.
     */
    public function parseWebhook(Request $request): PaymentResult;

    public function expire(Payment $payment): PaymentResult;

    public function supportsRefund(): bool;

    public function refund(Payment $payment, float $amount, ?string $reason = null): PaymentResult;
}
