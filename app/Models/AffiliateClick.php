<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateClick extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'utm' => 'array',
            'expires_at' => 'datetime',
            'converted' => 'boolean',
        ];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(AffiliateLink::class, 'affiliate_link_id');
    }
}
