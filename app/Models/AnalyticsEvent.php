<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    public const UPDATED_AT = null;

    public const STORE_VIEW = 'store_view';

    public const PRODUCT_VIEW = 'product_view';

    public const BLOCK_IMPRESSION = 'block_impression';

    public const BLOCK_CLICK = 'block_click';

    public const ADD_TO_CART = 'add_to_cart';

    public const BEGIN_CHECKOUT = 'begin_checkout';

    public const PURCHASE = 'purchase';

    public const LEAD = 'lead_submission';

    public const AFFILIATE_CLICK = 'affiliate_click';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'value' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
