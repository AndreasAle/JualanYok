<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginOtp;
use App\Notifications\EmailVerificationCode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The last step of setting up a shop: proving the email works.
 *
 * A code, not a link. Verification happens inside the wizard, and a link would
 * open a new tab and abandon the four steps just filled in — so the six digits
 * come back into the page the creator is already looking at.
 *
 * This matters more here than in most products. Every receipt, every download
 * link for a digital order, and every "your buyer paid" alert goes to this
 * address. A shop whose owner's email bounces takes money and then goes quiet,
 * which is why publishing is gated on it rather than merely nagged about.
 */
class OnboardingVerificationController extends Controller
{
    public const PURPOSE = 'verify_email';

    private const TTL_MINUTES = 15;

    /** A new code is only worth sending once the last one is a minute old. */
    private const RESEND_SECONDS = 60;

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('creator.dashboard');
        }

        $latest = $this->latestCode($user->email);

        return Inertia::render('Onboarding/Verify', [
            'email' => $user->email,
            'storeName' => $user->store?->name,
            'resendInSeconds' => $this->resendIn($latest),
            'alreadySent' => $latest !== null,
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('creator.dashboard');
        }

        $wait = $this->resendIn($this->latestCode($user->email));

        if ($wait > 0) {
            // Told plainly rather than silently ignored: a button that appears
            // to do nothing gets pressed ten more times.
            throw ValidationException::withMessages([
                'code' => "Tunggu {$wait} detik lagi sebelum minta kode baru.",
            ]);
        }

        $code = (string) random_int(100000, 999999);

        LoginOtp::create([
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'purpose' => self::PURPOSE,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'ip_address' => $request->ip(),
        ]);

        $user->notify(new EmailVerificationCode($code));

        return back()->with('info', 'Kode verifikasi sudah dikirim ke '.$user->email);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('creator.dashboard');
        }

        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);

        $otp = $this->latestCode($user->email);

        if (! $otp || ! $otp->isUsable()) {
            throw ValidationException::withMessages([
                'code' => 'Kodenya sudah kedaluwarsa. Minta kode baru ya.',
            ]);
        }

        // Counted before it is checked, so a wrong guess costs an attempt even
        // if the response never gets read.
        $otp->increment('attempts');

        if (! $otp->matches($data['code'])) {
            throw ValidationException::withMessages(['code' => 'Kodenya salah. Cek lagi ya.']);
        }

        $otp->update(['consumed_at' => now()]);

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->route('creator.products.create', ['first' => 1])
            ->with('success', 'Email terverifikasi. Sekarang buat produk pertamamu.');
    }

    private function latestCode(string $email): ?LoginOtp
    {
        return LoginOtp::where('email', $email)
            ->where('purpose', self::PURPOSE)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();
    }

    private function resendIn(?LoginOtp $otp): int
    {
        if (! $otp) {
            return 0;
        }

        $elapsed = $otp->created_at->diffInSeconds(now());

        return (int) max(0, self::RESEND_SECONDS - $elapsed);
    }
}
