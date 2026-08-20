<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\Username;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->configured()) {
            return redirect()->route('login')->with(
                'error',
                'Login Google belum dikonfigurasi. Isi GOOGLE_CLIENT_ID dan GOOGLE_CLIENT_SECRET terlebih dahulu.',
            );
        }

        if ($request->filled('template')) {
            $request->session()->put('onboarding_template', $request->string('template')->toString());
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->configured()) {
            return redirect()->route('login')->with('error', 'Login Google belum dikonfigurasi.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
            $googleId = trim((string) $googleUser->getId());
            $email = Str::lower(trim((string) $googleUser->getEmail()));
            $name = trim((string) $googleUser->getName()) ?: Str::before($email, '@');

            if ($googleId === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Google tidak mengirim identitas akun yang valid.');
            }

            $isNew = false;

            $user = DB::transaction(function () use ($googleId, $email, $name, &$isNew): User {
                $user = User::withTrashed()->where('google_id', $googleId)->first()
                    ?? User::withTrashed()->where('email', $email)->first();

                if ($user?->trashed()) {
                    throw new RuntimeException('Akun ini sudah dihapus. Hubungi support untuk memulihkannya.');
                }

                if ($user && $user->google_id && $user->google_id !== $googleId) {
                    throw new RuntimeException('Email ini sudah terhubung dengan akun Google lain.');
                }

                if ($user) {
                    $user->forceFill([
                        'google_id' => $googleId,
                        'email_verified_at' => $user->email_verified_at ?? now(),
                    ])->save();

                    return $user;
                }

                $isNew = true;

                $user = User::create([
                    'name' => $name,
                    'username' => Username::suggestFrom($name),
                    'email' => $email,
                    'google_id' => $googleId,
                    'password' => Str::password(48),
                    'tos_accepted_at' => now(),
                ]);

                $user->forceFill([
                    'email_verified_at' => now(),
                    'status' => 'active',
                ])->save();

                $user->profile()->create(['display_name' => $name]);
                $user->roles()->attach(Role::where('slug', Role::CUSTOMER)->value('id'));
                $user->walletOrCreate();

                return $user;
            });

            if ($user->isSuspended()) {
                throw new RuntimeException('Akun kamu sedang ditangguhkan. Hubungi support ya.');
            }

            if ($isNew) {
                event(new Registered($user));
            }

            Auth::login($user, remember: true);
            $request->session()->regenerate();
            $user->forceFill(['last_login_at' => now()])->save();

            return redirect()->intended($isNew ? route('onboarding.index') : $this->homeFor($user));
        } catch (Throwable $exception) {
            Log::warning('auth.google.failed', [
                'message' => $exception->getMessage(),
                'ip' => $request->ip(),
            ]);

            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Login Google gagal atau dibatalkan. Silakan coba lagi.';

            return redirect()->route('login')->with('error', $message);
        }
    }

    private function configured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    private function homeFor(User $user): string
    {
        return match (true) {
            $user->isAdmin() => route('admin.dashboard'),
            (bool) $user->store => route('creator.dashboard'),
            (bool) $user->is_affiliate => route('affiliate.dashboard'),
            default => route('member.dashboard'),
        };
    }
}
