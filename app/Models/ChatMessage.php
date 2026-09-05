<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    public const BUYER = 'buyer';

    public const SELLER = 'seller';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['context' => 'array', 'read_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
