<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function hasAccess(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /** Recomputes the cached percentage from the per-lesson progress rows. */
    public function recalculateProgress(): int
    {
        $total = $this->course->lessons()->count();

        if ($total === 0) {
            return 0;
        }

        $done = $this->progress()->where('completed', true)->count();
        $percent = (int) round($done / $total * 100);

        $this->forceFill([
            'progress_percent' => $percent,
            'completed_at' => $percent >= 100 ? ($this->completed_at ?? now()) : null,
        ])->save();

        return $percent;
    }
}
