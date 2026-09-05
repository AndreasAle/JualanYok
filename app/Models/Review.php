<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    public const PUBLISHED = 'PUBLISHED';

    public const HIDDEN = 'HIDDEN';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_anonymous' => 'boolean',
            'seller_replied_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ReviewMedia::class)->orderBy('position');
    }

    /**
     * The name to show.
     *
     * Shortened the way marketplaces do — "Andreas A." — because a full name
     * beside a public purchase history is more than a buyer signed up for.
     */
    public function displayName(): string
    {
        if ($this->is_anonymous) {
            return 'Pembeli';
        }

        $parts = preg_split('/\s+/', trim($this->author_name)) ?: [];
        $first = $parts[0] ?? 'Pembeli';

        return count($parts) > 1
            ? $first.' '.mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1)).'.'
            : $first;
    }

    public function avatarUrl(): ?string
    {
        return $this->is_anonymous ? null : $this->author?->avatarUrl();
    }

    public function mediaUrls(): array
    {
        return $this->media
            ->map(fn (ReviewMedia $item) => [
                'url' => Media::url($item->path),
                'kind' => $item->kind,
            ])
            ->all();
    }
}
