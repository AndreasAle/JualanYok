<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the whole creator dashboard. A user without a store is redirected to
 * onboarding rather than shown an empty dashboard.
 */
class EnsureIsCreator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user, 403);

        if ($user->isSuspended()) {
            abort(403, 'Akun kamu sedang ditangguhkan. Hubungi support ya.');
        }

        if (! $user->store) {
            return redirect()->route('onboarding.index');
        }

        return $next($request);
    }
}
