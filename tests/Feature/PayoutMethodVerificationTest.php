<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PayoutMethod;
use App\Models\Role;
use App\Notifications\PayoutMethodReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PayoutMethodVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_only_finance_and_super_admin_can_access_payout_method_verification(): void
    {
        $finance = $this->makeUser([Role::FINANCE_ADMIN]);
        $superAdmin = $this->makeUser([Role::SUPER_ADMIN]);
        $support = $this->makeUser([Role::SUPPORT_ADMIN]);
        $creator = $this->makeUser([Role::CREATOR]);

        $this->actingAs($finance)->get('/admin/rekening-pencairan')->assertOk();
        $this->actingAs($superAdmin)->get('/admin/rekening-pencairan')->assertOk();
        $this->actingAs($support)->get('/admin/rekening-pencairan')->assertForbidden();
        $this->actingAs($creator)->get('/admin/rekening-pencairan')->assertForbidden();
    }

    public function test_finance_can_review_full_account_details_and_approve_with_an_audit_trail(): void
    {
        Notification::fake();

        $finance = $this->makeUser([Role::FINANCE_ADMIN]);
        $creator = $this->makeUser([Role::CREATOR], [
            'name' => 'Pemilik Rekening',
            'phone' => '081234567890',
        ]);
        $method = $this->makePayoutMethod($creator->id);

        $this->actingAs($finance)
            ->get('/admin/rekening-pencairan')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/PayoutMethods')
                ->where('stats.pending', 1)
                ->where('methods.data.0.user.name', 'Pemilik Rekening')
                ->where('methods.data.0.user.phone', '081234567890')
                ->where('methods.data.0.account_number', '1234567890123456'));

        $this->actingAs($finance)
            ->post("/admin/rekening-pencairan/{$method->id}/setujui", [
                'note' => 'Nama dan nomor tujuan sudah cocok.',
            ])
            ->assertRedirect();

        $method->refresh();

        $this->assertSame('verified', $method->status);
        $this->assertSame($finance->id, $method->reviewed_by);
        $this->assertSame('Nama dan nomor tujuan sudah cocok.', $method->review_note);
        $this->assertNotNull($method->verified_at);
        $this->assertNotNull($method->reviewed_at);

        $audit = AuditLog::where('action', 'payout_method.verified')->sole();
        $this->assertSame($finance->id, $audit->user_id);
        $this->assertSame($method->id, $audit->auditable_id);
        $this->assertSame('unverified', $audit->before['status']);
        $this->assertSame('verified', $audit->after['status']);
        $this->assertArrayNotHasKey('account_number', $audit->after);

        Notification::assertSentTo(
            $creator,
            PayoutMethodReviewed::class,
            fn (PayoutMethodReviewed $notification) => $notification->method->is($method),
        );
    }

    public function test_finance_can_reject_with_a_required_reason_and_creator_can_see_it(): void
    {
        Notification::fake();

        $finance = $this->makeUser([Role::FINANCE_ADMIN]);
        $creator = $this->makeUser([Role::CREATOR]);
        $this->makeStore($creator);
        $method = $this->makePayoutMethod($creator->id);

        $this->actingAs($finance)
            ->post("/admin/rekening-pencairan/{$method->id}/tolak", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame('unverified', $method->fresh()->status);

        $this->actingAs($finance)
            ->post("/admin/rekening-pencairan/{$method->id}/tolak", [
                'reason' => 'Nama pemilik tidak sama dengan identitas akun.',
            ])
            ->assertRedirect();

        $method->refresh();

        $this->assertSame('rejected', $method->status);
        $this->assertSame($finance->id, $method->reviewed_by);
        $this->assertSame('Nama pemilik tidak sama dengan identitas akun.', $method->review_note);
        $this->assertNull($method->verified_at);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $finance->id,
            'action' => 'payout_method.rejected',
            'auditable_id' => $method->id,
            'reason' => 'Nama pemilik tidak sama dengan identitas akun.',
        ]);

        $this->actingAs($creator)
            ->get('/dashboard/penarikan')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('payoutMethods.0.status', 'rejected')
                ->where('payoutMethods.0.review_note', 'Nama pemilik tidak sama dengan identitas akun.'));

        Notification::assertSentTo($creator, PayoutMethodReviewed::class);
    }

    public function test_support_admin_cannot_approve_and_verified_method_cannot_be_rejected(): void
    {
        $support = $this->makeUser([Role::SUPPORT_ADMIN]);
        $finance = $this->makeUser([Role::FINANCE_ADMIN]);
        $creator = $this->makeUser([Role::CREATOR]);
        $method = $this->makePayoutMethod($creator->id);

        $this->actingAs($support)
            ->post("/admin/rekening-pencairan/{$method->id}/setujui")
            ->assertForbidden();

        $this->assertSame('unverified', $method->fresh()->status);

        $method->update(['status' => 'verified', 'verified_at' => now()]);

        $this->actingAs($finance)
            ->post("/admin/rekening-pencairan/{$method->id}/tolak", [
                'reason' => 'Rekening ini hendak ditolak kembali.',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('verified', $method->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'payout_method.rejected']);
    }

    private function makePayoutMethod(int $userId): PayoutMethod
    {
        return PayoutMethod::create([
            'user_id' => $userId,
            'type' => 'bank',
            'provider' => 'BCA',
            'account_name' => 'Pemilik Rekening',
            'account_number' => '1234567890123456',
            'account_number_last4' => '3456',
            'is_default' => true,
            'status' => 'unverified',
        ]);
    }
}
