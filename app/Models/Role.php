<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const CUSTOMER = 'customer';

    public const CREATOR = 'creator';

    public const AFFILIATE = 'affiliate';

    public const TEAM = 'team';

    public const SUPPORT_ADMIN = 'support-admin';

    public const FINANCE_ADMIN = 'finance-admin';

    public const SUPER_ADMIN = 'super-admin';

    protected $guarded = [];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }
}
