<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AffiliateLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public static function generateCode(): string
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(AffiliateProgram::class, 'affiliate_program_id');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function shareUrl(): string
    {
        $store = $this->program->store;
        $base = $this->product
            ? url("/{$store->username}/p/{$this->product->slug}")
            : url("/{$store->username}");

        $query = ['ref' => $this->code];
        if ($this->campaign) {
            $query['campaign'] = $this->campaign;
        }
        if ($this->sub_id) {
            $query['sub_id'] = $this->sub_id;
        }

        return $base.'?'.http_build_query($query);
    }

    public function conversionRate(): float
    {
        return $this->clicks > 0 ? round($this->conversions / $this->clicks * 100, 2) : 0.0;
    }
}
