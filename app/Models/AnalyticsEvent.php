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

    public const MARKETPLACE_HOME_VIEW = 'marketplace_home_view';

    public const CATEGORY_VIEW = 'marketplace_category_view';

    public const SEARCH = 'marketplace_search';

    public const SEARCH_RESULT_IMPRESSION = 'marketplace_search_result_impression';

    public const PRODUCT_IMPRESSION = 'marketplace_product_impression';

    public const PRODUCT_CLICK = 'marketplace_product_click';

    public const CREATOR_CLICK = 'marketplace_creator_click';

    public const SHARE = 'share';

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
