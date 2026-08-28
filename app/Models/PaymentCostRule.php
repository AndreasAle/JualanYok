<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentCostRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fee_percent' => 'decimal:4',
            'fee_fixed' => 'decimal:2',
            'minimum_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'settlement_days' => 'integer',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
