<?php

namespace Tests\Feature;

use App\Models\LoginOtp;
use App\Models\Store;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Proving the creator's email before the shop goes live.
 *
 * This is not a formality. Receipts, the download link for a digital order, and
 * every "your buyer paid" alert are sent to that address — a shop whose owner's
 * email bounces takes money and then goes quiet, and the buyer is the one left
 * with nothing. So publishing is gated on it, not merely nagged about.
 */
class OnboardingVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        $this->store = $this->makeStore();
        $this->creator = $this->store->owner;
        $this->creator->forceFill(['email_verified_at' => null])->save();
        $this->store->forceFill(['is_published' => false])->save();
    }

    private function issueCode(string $code = '123456'): LoginOtp
    {
        return LoginOtp::create([
            'email' => $this->creator->email,
            'code_hash' => Hash::make($code),
            'purpose' => 'verify_email',
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    public function test_the_verification_step_is_shown_to_an_unverified_creator(): void
    {
        $this->actingAs($this->creator)
            ->get('/onboarding/verifikasi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Onboarding/Verify')
                ->where('email', $this->creator->email));
    }

    public function test_an_already_verified_creator_is_not_asked_again(): void
    {
        $this->creator->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($this->creator)
            ->get('/onboarding/verifikasi')
            ->assertRedirect(route('creator.dashboard'));
    }

    public function test_a_code_is_emailed_on_request(): void
    {
        Notification::fake();

        $this->actingAs($this->creator)->post('/onboarding/verifikasi/kirim')->assertRedirect();

        Notification::assertSentTo($this->creator, EmailVerificationCode::class);

        $otp = LoginOtp::firstOrFail();
        $this->assertSame('verify_email', $otp->purpose);

        // Only the hash is kept; a leaked table must not hand out live codes.
        $this->assertNotEmpty($otp->code_hash);
        $this->assertStringNotContainsString('123456', $otp->code_hash);
    }

    public function test_a_second_code_is_refused_until_the_cooldown_passes(): void
    {
        Notification::fake();

        $this->actingAs($this->creator)->post('/onboarding/verifikasi/kirim');

        $this->actingAs($this->creator)
            ->post('/onboarding/verifikasi/kirim')
            ->assertSessionHasErrors('code');

        $this->assertSame(1, LoginOtp::count());
    }

    public function test_the_right_code_verifies_and_moves_on(): void
    {
        $this->issueCode();

        $this->actingAs($this->creator)
            ->post('/onboarding/verifikasi', ['code' => '123456'])
            ->assertRedirect(route('creator.products.create', ['first' => 1]));

        $this->assertNotNull($this->creator->fresh()->email_verified_at);
        $this->assertNotNull(LoginOtp::firstOrFail()->consumed_at);
    }

    public function test_a_wrong_code_costs_an_attempt(): void
    {
        $otp = $this->issueCode();

        $this->actingAs($this->creator)
            ->post('/onboarding/verifikasi', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, $otp->fresh()->attempts);
        $this->assertNull($this->creator->fresh()->email_verified_at);
    }

    public function test_guessing_runs_out(): void
    {
        $otp = $this->issueCode();
        $otp->forceFill(['attempts' => LoginOtp::MAX_ATTEMPTS])->save();

        $this->actingAs($this->creator)
            ->post('/onboarding/verifikasi', ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->assertNull($this->creator->fresh()->email_verified_at);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $otp = $this->issueCode();
        $otp->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->actingAs($this->creator)
            ->post('/onboarding/verifikasi', ['code' => '123456'])
            ->assertSessionHasErrors('code');
    }

    public function test_one_creators_code_cannot_verify_another_account(): void
    {
        $this->issueCode();

        $outsider = $this->makeStore(null, ['username' => 'tokolain'])->owner;
        $outsider->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($outsider)
            ->post('/onboarding/verifikasi', ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->assertNull($outsider->fresh()->email_verified_at);
    }

    public function test_a_verification_code_cannot_be_used_to_sign_in_as_a_buyer(): void
    {
        // Different purposes, different powers. A code that proves an address
        // works is not a credential that opens a session.
        $this->issueCode();

        $this->post('/masuk-pembeli/verifikasi', [
            'email' => $this->creator->email,
            'code' => '123456',
        ])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_an_unverified_store_cannot_be_published(): void
    {
        $this->store->blocks()->create(['type' => 'TEXT', 'content' => ['body' => 'Halo'], 'position' => 0]);

        $this->actingAs($this->creator)
            ->post('/dashboard/toko/publish')
            ->assertRedirect();

        $this->assertFalse((bool) $this->store->fresh()->is_published);
    }

    public function test_the_same_store_publishes_once_the_email_is_verified(): void
    {
        $this->store->blocks()->create(['type' => 'TEXT', 'content' => ['body' => 'Halo'], 'position' => 0]);
        $this->creator->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($this->creator)->post('/dashboard/toko/publish');

        $this->assertTrue((bool) $this->store->fresh()->is_published);
    }
}
