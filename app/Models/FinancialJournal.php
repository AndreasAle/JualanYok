<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinancialJournal extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'posted_at' => 'datetime'];
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function postings(): HasMany
    {
        return $this->hasMany(FinancialPosting::class);
    }
}
