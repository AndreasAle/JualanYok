<?php

namespace App\Models;

use App\Enums\BlockType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Block extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => BlockType::class,
            'content' => 'array',
            'draft_content' => 'array',
            'style' => 'array',
            'is_published' => 'boolean',
            'visible_mobile' => 'boolean',
            'visible_desktop' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BlockVersion::class);
    }

    /** Blocks a public visitor is allowed to see right now. */
    public function scopeVisibleNow(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    public function isVisibleNow(): bool
    {
        if (! $this->is_published) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    /** Editor works on draft_content; the public site reads content. */
    public function editorContent(): array
    {
        return $this->draft_content ?? $this->content ?? [];
    }

    public function hasUnpublishedChanges(): bool
    {
        return $this->draft_content !== null && $this->draft_content != $this->content;
    }
}
