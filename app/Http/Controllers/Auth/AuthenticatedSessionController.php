<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => $request->session()->get('status'),
            'canResetPassword' => true,
            'googleConfigured' => filled(config('services.google.client_id'))
                && filled(config('services.google.client_secret')),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string'],   // email or username
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $throttleKey = Str::lower($data['login']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'login' => 'Terlalu banyak percobaan. Coba lagi dalam '
                    .ceil(RateLimiter::availableIn($throttleKey) / 60).' menit.',
            ]);
        }

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => Str::lower($data['login']), 'password' => $data['password']], $data['remember'] ?? false)) {
            RateLimiter::hit($throttleKey, 300);

            throw ValidationException::withMessages([
                'login' => 'Email/username atau password salah.',
            ]);
        }

        $user = Auth::user();

        if ($user->isSuspended()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'login' => 'Akun kamu sedang ditangguhkan. Hubungi support ya.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended($this->homeFor($user));
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /** Invalidates every other session for this account. */
    public function logoutOtherDevices(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']]);

        Auth::logoutOtherDevices($request->string('password'));

        return back()->with('success', 'Semua perangkat lain sudah dikeluarkan.');
    }

    private function homeFor($user): string
    {
        return match (true) {
            $user->isAdmin() => route('admin.dashboard'),
            (bool) $user->store => route('creator.dashboard'),
            (bool) $user->is_affiliate => route('affiliate.dashboard'),
            default => route('member.dashboard'),
        };
    }
}
