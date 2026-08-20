<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayoutMethod extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['account_number'];

    protected function casts(): array
    {
        return [
            // Full account numbers are encrypted at rest; the UI only ever
            // renders the last four digits stored alongside.
            'account_number' => 'encrypted',
            'is_default' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function maskedNumber(): string
    {
        return '•••• '.($this->account_number_last4 ?? '????');
    }

    public function label(): string
    {
        return sprintf('%s — %s (%s)', $this->provider, $this->maskedNumber(), $this->account_name);
    }
}
