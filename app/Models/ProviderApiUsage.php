<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderApiUsage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
