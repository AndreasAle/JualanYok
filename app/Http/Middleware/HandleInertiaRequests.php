<?php

namespace App\Http\Middleware;

use App\Services\PlanService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props shared with every page. Kept deliberately small — anything heavy
     * is lazy so it is only computed for the pages that ask for it.
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'app' => [
                'name' => config('jualanyok.name'),
                'demo' => (bool) config('jualanyok.demo.enabled'),
            ],

            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'avatar_url' => $user->avatarUrl(),
                    'is_creator' => (bool) $user->is_creator,
                    'is_affiliate' => (bool) $user->is_affiliate,
                    'is_admin' => $user->isAdmin(),
                    'is_super_admin' => $user->isSuperAdmin(),
                    'roles' => $user->roleSlugs()->values(),
                    'email_verified' => $user->hasVerifiedEmail(),
                ] : null,

                'store' => $user?->store ? [
                    'id' => $user->store->id,
                    'username' => $user->store->username,
                    'name' => $user->store->name,
                    'is_published' => (bool) $user->store->is_published,
                    'public_url' => $user->store->publicUrl(),
                    'avatar_url' => $user->store->avatarUrl(),
                ] : null,

                'plan' => $user ? fn () => app(PlanService::class)->snapshot($user) : null,

                // Truthy only while a super admin is signed in as someone else.
                'impersonating' => (bool) $request->session()->get('impersonator_id'),
            ],

            'notifications' => $user
                ? fn () => $user->unreadNotifications()->latest()->limit(10)->get()
                    ->map(fn ($n) => [
                        'id' => $n->id,
                        'title' => $n->data['title'] ?? 'Notifikasi',
                        'message' => $n->data['message'] ?? '',
                        'url' => $n->data['url'] ?? null,
                        'created_at' => $n->created_at->diffForHumans(),
                    ])
                : [],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],

            'ziggy' => fn () => [
                'location' => $request->url(),
            ],
        ]);
    }
}
