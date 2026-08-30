<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Support\Media;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'username', 'email', 'google_id', 'phone', 'password', 'avatar_path',
        'tos_accepted_at', 'is_creator', 'is_affiliate', 'timezone', 'locale',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'tos_accepted_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_creator' => 'boolean',
            'is_affiliate' => 'boolean',
        ];
    }

    /* --------------------------------------------------------------------
     | Relations
     -------------------------------------------------------------------- */

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function store(): HasOne
    {
        return $this->hasOne(Store::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function payoutMethods(): HasMany
    {
        return $this->hasMany(PayoutMethod::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function affiliateLinks(): HasMany
    {
        return $this->hasMany(AffiliateLink::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function notificationDigestState(): HasOne
    {
        return $this->hasOne(NotificationDigestState::class);
    }

    public function loginDevices(): HasMany
    {
        return $this->hasMany(UserLoginDevice::class);
    }

    /* --------------------------------------------------------------------
     | Authorization helpers
     |
     | Role checks always hit the database (cached per request) rather than a
     | session flag, so a revoked role takes effect immediately.
     -------------------------------------------------------------------- */

    public function hasRole(string ...$slugs): bool
    {
        return $this->roleSlugs()->intersect($slugs)->isNotEmpty();
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->hasRole(Role::SUPER_ADMIN)) {
            return true;
        }

        return $this->relationLoaded('roles')
            ? $this->roles->pluck('permissions')->flatten()->pluck('slug')->contains($slug)
            : $this->roles()->whereHas('permissions', fn ($q) => $q->where('slug', $slug))->exists();
    }

    public function roleSlugs(): Collection
    {
        return $this->relationLoaded('roles')
            ? $this->roles->pluck('slug')
            : $this->roles()->pluck('slug');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN, Role::SUPPORT_ADMIN, Role::FINANCE_ADMIN);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    public function isSuspended(): bool
    {
        return $this->status !== 'active';
    }

    /* --------------------------------------------------------------------
     | Subscription
     -------------------------------------------------------------------- */

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', collect(SubscriptionStatus::cases())
                ->filter(fn ($s) => $s->grantsAccess())
                ->map(fn ($s) => $s->value))
            ->latest('id')
            ->first();
    }

    public function currentPlan(): Plan
    {
        return $this->activeSubscription()?->plan ?? Plan::free();
    }

    /** Ensures the wallet row exists before any ledger write touches it. */
    public function walletOrCreate(string $currency = 'IDR'): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $this->id, 'currency' => $currency],
        );
    }

    public function avatarUrl(): ?string
    {
        return Media::url($this->avatar_path);
    }
}
