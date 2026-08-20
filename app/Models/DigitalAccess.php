<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DigitalAccess extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_revoked' => 'boolean',
        ];
    }

    public static function generateToken(): string
    {
        return Str::random(48);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(ProductFile::class, 'product_file_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }

    /**
     * Every condition that must hold before a signed download URL is honoured.
     * Checked again at download time, not just when the link is generated.
     */
    public function isDownloadable(): bool
    {
        if ($this->is_revoked) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->download_limit !== null && $this->download_count >= $this->download_limit) {
            return false;
        }

        return true;
    }

    public function remainingDownloads(): ?int
    {
        return $this->download_limit === null
            ? null
            : max(0, $this->download_limit - $this->download_count);
    }
}
