<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsAdmin
{
    /**
     * @param  string  ...$roles  Optional role slugs; defaults to any admin role.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user, 403);

        $allowed = $roles ?: [Role::SUPER_ADMIN, Role::SUPPORT_ADMIN, Role::FINANCE_ADMIN];

        // Super admin always passes, even for role-specific panels.
        abort_unless($user->hasRole(...$allowed) || $user->isSuperAdmin(), 403);

        return $next($request);
    }
}
