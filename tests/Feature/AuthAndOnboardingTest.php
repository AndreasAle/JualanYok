<?php

namespace Tests\Feature;

use App\Models\LoginOtp;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Database\Seeders\StorefrontTemplateSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAndOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_a_visitor_can_register_and_lands_on_onboarding(): void
    {
        $response = $this->post('/register', [
            'name' => 'Ayu Prameswari',
            'username' => 'ayuprames',
            'email' => 'ayu@example.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'terms' => true,
        ]);

        $response->assertRedirect('/onboarding');
        $this->assertAuthenticated();

        $user = User::where('email', 'ayu@example.test')->firstOrFail();

        $this->assertNotNull($user->wallet, 'A wallet is created up front.');
        $this->assertTrue($user->hasRole(Role::CUSTOMER));
        $this->assertNotNull($user->tos_accepted_at);
    }

    public function test_username_must_be_unique_across_users_and_stores(): void
    {
        $this->makeUser([Role::CUSTOMER], ['username' => 'sudahdipakai', 'email' => 'a@example.test']);

        $this->post('/register', [
            'name' => 'Orang Lain',
            'username' => 'sudahdipakai',
            'email' => 'b@example.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'terms' => true,
        ])->assertSessionHasErrors('username');
    }

    public function test_reserved_usernames_are_refused(): void
    {
        $this->post('/register', [
            'name' => 'Penyusup',
            'username' => 'admin',
            'email' => 'admin-wannabe@example.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'terms' => true,
        ])->assertSessionHasErrors('username');
    }

    public function test_username_availability_endpoint_reports_status(): void
    {
        $this->makeUser([Role::CUSTOMER], ['username' => 'terpakai', 'email' => 'c@example.test']);

        $this->postJson('/username/check', ['username' => 'tersediakok'])
            ->assertOk()
            ->assertJson(['available' => true]);

        $this->postJson('/username/check', ['username' => 'terpakai'])
            ->assertOk()
            ->assertJson(['available' => false]);

        $this->postJson('/username/check', ['username' => 'dashboard'])
            ->assertOk()
            ->assertJson(['available' => false]);
    }

    public function test_registration_requires_accepting_the_terms(): void
    {
        $this->post('/register', [
            'name' => 'Tanpa Setuju',
            'username' => 'tanpasetuju',
            'email' => 'no-terms@example.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('terms');
    }

    public function test_login_works_with_username_or_email(): void
    {
        $user = $this->makeUser([Role::CUSTOMER], [
            'username' => 'masukdulu',
            'email' => 'masuk@example.test',
        ]);

        $this->post('/login', ['login' => 'masuk@example.test', 'password' => 'password123'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');

        $this->post('/login', ['login' => 'masukdulu', 'password' => 'password123'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_suspended_user_cannot_log_in(): void
    {
        $user = $this->makeUser([Role::CUSTOMER], ['email' => 'suspended@example.test']);
        $user->forceFill(['status' => 'suspended', 'suspension_reason' => 'Melanggar ketentuan.'])->save();

        $this->post('/login', ['login' => 'suspended@example.test', 'password' => 'password123'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_onboarding_creates_a_store_from_a_template(): void
    {
        $this->seed(StorefrontTemplateSeeder::class);

        $user = $this->makeUser([Role::CUSTOMER], ['username' => 'calonkreator']);

        $this->actingAs($user)->post('/onboarding', [
            'goal' => 'digital',
            'niche' => 'Content Creator',
            'template' => 'creator-digital',
            'store_name' => 'KreatorKita',
            'username' => 'kreatorkita',
            'tagline' => 'Bikin konten konsisten',
            'publish' => true,
        ])->assertRedirect('/dashboard/produk/create?first=1');

        $store = Store::where('username', 'kreatorkita')->firstOrFail();

        $this->assertFalse($store->is_published, 'A new store stays draft until its first product is previewed.');
        $this->assertTrue($store->blocks()->count() > 0, 'Template blocks are laid down.');
        $this->assertNotNull($store->theme);
        $this->assertTrue($user->fresh()->hasRole(Role::CREATOR));
    }

    public function test_a_creator_cannot_create_a_second_store(): void
    {
        $store = $this->makeStore();

        $this->actingAs($store->owner)->post('/onboarding', [
            'goal' => 'digital',
            'store_name' => 'Toko Kedua',
            'username' => 'tokokedua',
        ])->assertStatus(409);
    }

    public function test_otp_login_does_not_reveal_whether_an_email_exists(): void
    {
        $known = $this->makeUser([Role::CUSTOMER], ['email' => 'ada@example.test']);

        $first = $this->post('/masuk-pembeli', ['email' => 'ada@example.test']);
        $second = $this->post('/masuk-pembeli', ['email' => 'tidakada@example.test']);

        $first->assertRedirect('/masuk-pembeli/verifikasi');
        $second->assertRedirect('/masuk-pembeli/verifikasi');

        // A code is only actually issued for the address we know.
        $this->assertDatabaseHas('login_otps', ['email' => 'ada@example.test']);
        $this->assertDatabaseMissing('login_otps', ['email' => 'tidakada@example.test']);
    }

    public function test_login_code_is_sent_immediately_instead_of_waiting_for_a_queue_worker(): void
    {
        $notification = new LoginCodeNotification('123456');
        $mail = $notification->toMail((object) []);

        $this->assertNotInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame('Kode masuk JualanYok — berlaku 10 menit', $mail->subject);
        $this->assertSame([
            'html' => 'mail.auth.login-code',
            'text' => 'mail.auth.login-code-text',
        ], $mail->view);
        $this->assertSame('123456', $mail->viewData['code']);

        $html = view('mail.auth.login-code', $mail->viewData)->render();

        $this->assertStringContainsString('Satu langkah lagi untuk masuk.', $html);
        $this->assertStringContainsString('123456', $html);
        $this->assertStringNotContainsString('Laravel', $html);
    }

    public function test_a_wrong_otp_is_rejected(): void
    {
        $this->makeUser([Role::CUSTOMER], ['email' => 'otp@example.test']);
        $this->post('/masuk-pembeli', ['email' => 'otp@example.test']);

        $this->post('/masuk-pembeli/verifikasi', [
            'email' => 'otp@example.test',
            'code' => '000000',
        ])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_otp_login_sends_an_existing_creator_to_the_creator_dashboard(): void
    {
        $creator = $this->makeUser([Role::CUSTOMER, Role::CREATOR], [
            'email' => 'creator-otp@example.test',
            'is_creator' => true,
        ]);
        $this->makeStore($creator);

        LoginOtp::create([
            'email' => $creator->email,
            'code_hash' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(10),
            'ip_address' => '127.0.0.1',
        ]);

        $this->withSession(['otp_email' => $creator->email])
            ->post('/masuk-pembeli/verifikasi', [
                'email' => $creator->email,
                'code' => '654321',
            ])
            ->assertRedirect(route('creator.dashboard'));

        $this->assertAuthenticatedAs($creator);
        $this->assertNotNull($creator->fresh()->last_login_at);
    }
}
