<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The account emails people actually depend on: proving an address, getting
 * back in after forgetting a password, and hearing from us at all after signing
 * up with Google.
 */
class AuthEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_registering_sends_a_welcome_and_a_verification_email(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Rina Kreator',
            'username' => 'rinakreator',
            'email' => 'rina@example.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'terms' => true,
        ])->assertRedirect();

        $user = User::where('email', 'rina@example.test')->firstOrFail();

        Notification::assertSentTo($user, WelcomeNotification::class);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_a_google_signup_is_welcomed_but_not_asked_to_verify(): void
    {
        Notification::fake();

        // Mirrors what GoogleAuthController produces: a verified account.
        $user = User::create([
            'name' => 'Budi Google',
            'username' => 'budigoogle',
            'email' => 'budi@example.test',
            'google_id' => '1234567890',
            'password' => 'irrelevant-random',
            'tos_accepted_at' => now(),
        ]);

        $user->forceFill(['email_verified_at' => now(), 'status' => 'active'])->save();
        $user->roles()->attach(Role::where('slug', Role::CUSTOMER)->value('id'));

        event(new \Illuminate\Auth\Events\Registered($user));

        Notification::assertSentTo($user, WelcomeNotification::class);

        // Asking a Google user to "verify" an address Google already proved is
        // noise, and trains people to click verification links they did not ask for.
        Notification::assertNotSentTo($user, VerifyEmail::class);
    }

    public function test_the_welcome_email_says_so_when_the_account_came_from_google(): void
    {
        $user = $this->makeUser([Role::CREATOR], ['google_id' => '999']);

        $plain = (new WelcomeNotification(viaGoogle: true))->toMail($user);
        $rendered = implode(' ', array_map('strval', $plain->introLines));

        $this->assertStringContainsString('Google', $rendered);
        $this->assertSame('Selamat datang di JualanYok!', $plain->subject);
    }

    public function test_forgot_password_sends_a_reset_link_in_indonesian(): void
    {
        Notification::fake();

        $user = $this->makeUser([Role::CREATOR], ['email' => 'lupa@example.test']);

        $this->post('/forgot-password', ['email' => 'lupa@example.test'])
            ->assertRedirect();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);

            $this->assertSame('Atur ulang password JualanYok', $mail->subject);
            $this->assertSame('Atur Ulang Password', $mail->actionText);

            return true;
        });
    }

    public function test_the_verification_email_is_in_indonesian(): void
    {
        $user = $this->makeUser([Role::CREATOR], ['email_verified_at' => null]);

        $mail = (new VerifyEmail)->toMail($user);

        $this->assertSame('Konfirmasi alamat email kamu — JualanYok', $mail->subject);
        $this->assertSame('Konfirmasi Email', $mail->actionText);
    }

    public function test_forgot_password_does_not_reveal_whether_an_email_exists(): void
    {
        Notification::fake();

        $known = $this->makeUser([Role::CREATOR], ['email' => 'ada@example.test']);

        $first = $this->post('/forgot-password', ['email' => 'ada@example.test']);
        $second = $this->post('/forgot-password', ['email' => 'tidakada@example.test']);

        $this->assertSame($first->getStatusCode(), $second->getStatusCode());
        $this->assertSame(
            $first->getSession()->get('status'),
            $second->getSession()->get('status'),
            'A different response would let anyone enumerate registered emails.',
        );

        Notification::assertSentTo($known, ResetPassword::class);
    }

    public function test_a_password_reset_actually_lets_the_user_back_in(): void
    {
        Notification::fake();

        $user = $this->makeUser([Role::CREATOR], ['email' => 'pulih@example.test']);

        $this->post('/forgot-password', ['email' => 'pulih@example.test']);

        $token = null;

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'pulih@example.test',
            'password' => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('passwordbaru123', $user->fresh()->password),
            'The new password must actually be stored.',
        );

        // The login form accepts an email or a username under one `login` field.
        $this->post('/login', [
            'login' => 'pulih@example.test',
            'password' => 'passwordbaru123',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertAuthenticatedAs($user->fresh());
    }
}
