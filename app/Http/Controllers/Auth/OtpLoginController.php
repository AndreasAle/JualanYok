<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoginOtp;
use App\Models\Role;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use App\Support\Username;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Passwordless login for buyers. Most customers check out as guests, so the
 * member area is reached with a one-time code sent to the same email they used
 * at checkout — no account creation step.
 */
class OtpLoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/OtpRequest');
    }

    public function send(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $email = strtolower($data['email']);

        // Only addresses that actually bought something (or already have an
        // account) get a code — this endpoint must not confirm arbitrary emails.
        $known = User::where('email', $email)->exists()
            || Customer::where('email', $email)->exists();

        if ($known) {
            $code = (string) random_int(100000, 999999);

            LoginOtp::create([
                'email' => $email,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
                'ip_address' => $request->ip(),
            ]);

            Notification::route('mail', $email)->notify(new LoginCodeNotification($code));
        }

        $request->session()->put('otp_email', $email);

        // Identical response either way, so the endpoint cannot be used to
        // enumerate which emails exist.
        return redirect()->route('otp.form')
            ->with('info', 'Kalau email itu terdaftar, kode masuknya sudah kami kirim.');
    }

    public function verifyForm(Request $request): Response
    {
        return Inertia::render('Auth/OtpVerify', [
            'email' => $request->session()->get('otp_email'),
        ]);
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $email = strtolower($data['email']);

        $otp = LoginOtp::where('email', $email)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp || ! $otp->isUsable()) {
            throw ValidationException::withMessages(['code' => 'Kode sudah kedaluwarsa. Minta kode baru ya.']);
        }

        $otp->increment('attempts');

        if (! $otp->matches($data['code'])) {
            throw ValidationException::withMessages(['code' => 'Kode salah.']);
        }

        $otp->update(['consumed_at' => now()]);

        $user = User::where('email', $email)->first() ?? $this->createBuyerAccount($email);

        // Link every guest purchase made with this email to the account.
        Customer::where('email', $email)->whereNull('user_id')->update(['user_id' => $user->id]);

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $request->session()->forget('otp_email');
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended($this->homeFor($user));
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

    private function createBuyerAccount(string $email): User
    {
        $customer = Customer::where('email', $email)->latest('id')->first();
        $name = $customer?->name ?? Str::before($email, '@');

        $user = User::create([
            'name' => $name,
            'username' => Username::suggestFrom($name),
            'email' => $email,
            'password' => Str::random(40),   // unusable; login is via OTP
            'email_verified_at' => now(),
            'tos_accepted_at' => now(),
        ]);

        $user->roles()->attach(Role::where('slug', Role::CUSTOMER)->value('id'));
        $user->walletOrCreate();

        return $user;
    }
}
