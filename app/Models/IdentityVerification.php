<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A creator's identity check.
 *
 * The NIK is cast as encrypted, so it is ciphertext in the database and in any
 * backup taken from it. Nothing in the app ever hands the full number back to a
 * browser — support and the owner both see the masked form.
 */
class IdentityVerification extends Model
{
    public const PENDING = 'PENDING';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    protected $guarded = [];

    /** Never serialised into a page payload, even by accident. */
    protected $hidden = ['nik', 'id_card_path', 'selfie_path'];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
            'birth_date' => 'date',
            'consented_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }

    /** What a person is shown of their own number: enough to recognise it. */
    public function maskedNik(): string
    {
        return $this->nik_last4 ? '•••• •••• •••• '.$this->nik_last4 : '••••';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::APPROVED => 'Terverifikasi',
            self::REJECTED => 'Ditolak',
            default => 'Sedang ditinjau',
        };
    }
}
