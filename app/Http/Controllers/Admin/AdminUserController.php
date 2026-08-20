<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $users = User::with('roles:id,slug,name')
            ->withCount('stores')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where(fn ($s) => $s->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('username', 'like', $term));
            })
            ->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('slug', $request->query('role'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'email' => $u->email,
                'status' => $u->status,
                'is_creator' => (bool) $u->is_creator,
                'is_affiliate' => (bool) $u->is_affiliate,
                'roles' => $u->roles->pluck('slug'),
                'stores_count' => $u->stores_count,
                'created_at' => $u->created_at->toDateString(),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['q', 'role', 'status']),
            'roles' => Role::get(['slug', 'name']),
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $user->load(['roles', 'stores', 'wallet', 'payoutMethods']);

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'suspension_reason' => $user->suspension_reason,
                'roles' => $user->roles->pluck('slug'),
                'created_at' => $user->created_at->toDateTimeString(),
                'last_login_at' => $user->last_login_at?->toDateTimeString(),
                'stores' => $user->stores->map(fn ($s) => [
                    'username' => $s->username,
                    'name' => $s->name,
                    'is_published' => (bool) $s->is_published,
                    'status' => $s->status,
                ]),
                'wallet' => $user->wallet ? [
                    'pending' => (float) $user->wallet->pending_balance,
                    'available' => (float) $user->wallet->available_balance,
                    'held' => (float) $user->wallet->held_balance,
                    'withdrawn' => (float) $user->wallet->withdrawn_balance,
                    'is_frozen' => (bool) $user->wallet->is_frozen,
                ] : null,
            ],
            'canImpersonate' => $request->user()->isSuperAdmin() && ! $user->isSuperAdmin(),
        ]);
    }

    public function suspend(Request $request, User $user)
    {
        abort_if($user->isSuperAdmin(), 403, 'Super admin tidak bisa ditangguhkan.');

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        // status/suspension_reason are intentionally not mass-assignable, so
        // this admin-only path sets them explicitly.
        $user->forceFill([
            'status' => 'suspended',
            'suspension_reason' => $data['reason'],
        ])->save();
        $this->audit->log('user.suspended', $user, reason: $data['reason']);

        return back()->with('success', 'Pengguna ditangguhkan.');
    }

    public function reinstate(Request $request, User $user)
    {
        $user->forceFill(['status' => 'active', 'suspension_reason' => null])->save();
        $this->audit->log('user.reinstated', $user);

        return back()->with('success', 'Pengguna diaktifkan kembali.');
    }

    /**
     * Super-admin-only. The original admin id is kept in the session so the
     * impersonation banner can offer a way back, and both ends are audited.
     */
    public function impersonate(Request $request, User $user)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_if($user->isSuperAdmin(), 403, 'Tidak bisa impersonate sesama super admin.');

        $request->session()->put('impersonator_id', $request->user()->id);

        $this->audit->log('user.impersonate.start', $user);

        Auth::login($user);

        return redirect()->route('creator.dashboard')
            ->with('info', 'Kamu sedang menyamar sebagai '.$user->name.'.');
    }

    public function stopImpersonating(Request $request)
    {
        $impersonatorId = $request->session()->pull('impersonator_id');

        abort_unless($impersonatorId, 404);

        $this->audit->log('user.impersonate.stop', $request->user());

        Auth::loginUsingId($impersonatorId);

        return redirect()->route('admin.dashboard')->with('success', 'Kembali ke akun admin.');
    }
}
