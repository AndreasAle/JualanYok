<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_verified_super_admin_without_a_hardcoded_password(): void
    {
        $this->seedPlatform();

        $this->artisan('jualanyok:make-admin', [
            '--name' => 'Admin Production',
            '--email' => 'owner@example.com',
            '--username' => 'owner',
        ])
            ->expectsQuestion('Password admin (minimal 12 karakter)', 'a-secure-password')
            ->expectsQuestion('Ulangi password admin', 'a-secure-password')
            ->assertSuccessful();

        $admin = User::where('email', 'owner@example.com')->firstOrFail();

        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue($admin->hasRole(Role::SUPER_ADMIN));
        $this->assertNotNull($admin->email_verified_at);
        $this->assertSame('active', $admin->status);
        $this->assertTrue(Hash::check('a-secure-password', $admin->password));
    }
}
