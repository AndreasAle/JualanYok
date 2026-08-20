<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Lesson extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quiz' => 'array',
            'is_free_preview' => 'boolean',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /** Drip content unlocks a number of days after the student enrols. */
    public function isUnlockedFor(Enrollment $enrollment): bool
    {
        if ($this->drip_days === 0) {
            return true;
        }

        $start = $enrollment->started_at ?? $enrollment->created_at;

        return $start->copy()->addDays($this->drip_days)->isPast();
    }

    public function unlocksAt(Enrollment $enrollment): ?Carbon
    {
        if ($this->drip_days === 0) {
            return null;
        }

        return ($enrollment->started_at ?? $enrollment->created_at)->copy()->addDays($this->drip_days);
    }
}
