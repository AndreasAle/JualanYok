<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'seller_clawback' => 'decimal:2',
            'reserve_clawback' => 'decimal:2',
            'seller_debt_created' => 'decimal:2',
            'affiliate_clawback' => 'decimal:2',
            'affiliate_debt_created' => 'decimal:2',
            'platform_fee_reversal' => 'decimal:2',
            'shipping_reversal' => 'decimal:2',
            'tax_reversal' => 'decimal:2',
            'platform_loss' => 'decimal:2',
            'processed_at' => 'datetime',
            'approved_at' => 'datetime',
            'provider_response' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
