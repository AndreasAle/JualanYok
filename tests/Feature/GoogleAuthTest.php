<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_google_login_reports_when_credentials_are_not_configured(): void
    {
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);

        $this->get('/auth/google')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
    }

    public function test_google_callback_creates_a_complete_customer_account(): void
    {
        $this->fakeGoogleUser('google-new-123', 'ayu.google@example.test', 'Ayu Google');

        $this->get('/auth/google/callback')->assertRedirect(route('onboarding.index'));

        $user = User::where('email', 'ayu.google@example.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-new-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->tos_accepted_at);
        $this->assertNotNull($user->profile);
        $this->assertNotNull($user->wallet);
        $this->assertTrue($user->hasRole(Role::CUSTOMER));
    }

    public function test_google_callback_links_an_existing_account_by_verified_email(): void
    {
        $user = $this->makeUser([Role::CUSTOMER], ['email' => 'existing@example.test']);
        $this->fakeGoogleUser('google-existing-456', 'existing@example.test', 'Nama dari Google');

        $this->get('/auth/google/callback')->assertRedirect(route('member.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-existing-456', $user->fresh()->google_id);
        $this->assertSame('Test User', $user->fresh()->name, 'Existing profile data is not overwritten.');
    }

    private function fakeGoogleUser(string $id, string $email, string $name): void
    {
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-client-secret');

        $oauthUser = Mockery::mock(SocialiteUser::class);
        $oauthUser->shouldReceive('getId')->andReturn($id);
        $oauthUser->shouldReceive('getEmail')->andReturn($email);
        $oauthUser->shouldReceive('getName')->andReturn($name);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($oauthUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
    }
}
