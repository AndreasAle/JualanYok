<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use Illuminate\Support\Carbon;

/** Normalised gateway response shared by every adapter. */
final class PaymentResult
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $reference = null,
        public readonly ?float $amount = null,
        public readonly ?float $fee = null,
        public readonly array $instructions = [],
        public readonly ?string $redirectUrl = null,
        public readonly ?Carbon $expiresAt = null,
        public readonly ?Carbon $paidAt = null,
        public readonly ?string $eventId = null,
        public readonly ?string $error = null,
        public readonly array $raw = [],
    ) {}

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    public function failed(): bool
    {
        return in_array($this->status, [PaymentStatus::Failed, PaymentStatus::Expired], true);
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'reference' => $this->reference,
            'amount' => $this->amount,
            'fee' => $this->fee,
            'instructions' => $this->instructions,
            'redirect_url' => $this->redirectUrl,
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'paid_at' => $this->paidAt?->toIso8601String(),
            'event_id' => $this->eventId,
            'error' => $this->error,
            'raw' => $this->raw,
        ];
    }
}
