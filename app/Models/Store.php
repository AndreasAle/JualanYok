<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'socials' => 'array',
            'pixels' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'show_platform_branding' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'username';
    }

    /* ------------------------------------------------------------------ */

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function theme(): HasOne
    {
        return $this->hasOne(StoreTheme::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(StorefrontTemplate::class, 'storefront_template_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class)->orderBy('position');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(StoreDomain::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function affiliatePrograms(): HasMany
    {
        return $this->hasMany(AffiliateProgram::class);
    }

    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }

    public function analyticsSummaries(): HasMany
    {
        return $this->hasMany(AnalyticsSummary::class);
    }

    public function marketingConsents(): HasMany
    {
        return $this->hasMany(MarketingConsent::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /* ------------------------------------------------------------------ */

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_published', true)->where('status', 'active');
    }

    public function isLive(): bool
    {
        return $this->is_published && $this->status === 'active';
    }

    public function publicUrl(): string
    {
        $primary = $this->relationLoaded('domains')
            ? $this->domains->firstWhere(fn ($d) => $d->is_primary && $d->status === 'verified')
            : $this->domains()->where('is_primary', true)->where('status', 'verified')->first();

        return $primary ? 'https://'.$primary->domain : url('/'.$this->username);
    }

    public function avatarUrl(): ?string
    {
        return Media::url($this->avatar_path);
    }

    public function coverUrl(): ?string
    {
        return Media::url($this->cover_path);
    }
}
