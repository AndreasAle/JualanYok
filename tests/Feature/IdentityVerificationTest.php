<?php

namespace Tests\Feature;

use App\Enums\BalanceBucket;
use App\Enums\LedgerEntryType;
use App\Models\IdentityVerification;
use App\Models\PayoutMethod;
use App\Models\Role;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Knowing who is being paid.
 *
 * A payout sends real money to a named bank account, so the platform has to
 * know whose account it is. The person handing over a photograph of their ID
 * is owed something in return: it is encrypted, it never lands anywhere public,
 * and every look at it leaves a trace.
 */
class IdentityVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        Storage::fake('local');

        $this->creator = $this->makeStore()->owner;
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Siti Rahmawati',
            'nik' => '3201234567890123',
            'birth_place' => 'Bandung',
            'birth_date' => '1995-04-17',
            'address' => 'Jl. Melati No. 12, RT 03/RW 05, Cibaduyut, Bandung',
            'id_card' => UploadedFile::fake()->image('ktp.jpg'),
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'consent' => true,
        ], $overrides);
    }

    public function test_the_id_number_is_encrypted_and_the_photos_are_never_public(): void
    {
        Storage::fake('public');

        $this->actingAs($this->creator)
            ->post('/dashboard/verifikasi-identitas', $this->payload())
            ->assertRedirect();

        $row = IdentityVerification::firstOrFail();

        $this->assertSame('3201234567890123', $row->nik);
        // A database dump must not be a list of identity numbers.
        $stored = DB::table('identity_verifications')->value('nik');
        $this->assertNotSame('3201234567890123', $stored);
        $this->assertSame('0123', $row->nik_last4);

        foreach ([$row->id_card_path, $row->selfie_path] as $path) {
            Storage::disk('local')->assertExists($path);
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_consent_is_recorded_not_assumed(): void
    {
        $this->actingAs($this->creator)->post('/dashboard/verifikasi-identitas', $this->payload());

        $row = IdentityVerification::firstOrFail();

        $this->assertNotNull($row->consented_at);
        $this->assertNotNull($row->consent_ip);
    }

    public function test_it_refuses_to_take_the_documents_without_consent(): void
    {
        $this->actingAs($this->creator)
            ->post('/dashboard/verifikasi-identitas', $this->payload(['consent' => false]))
            ->assertSessionHasErrors('consent');

        $this->assertSame(0, IdentityVerification::count());
    }

    public function test_a_number_that_is_not_a_nik_is_refused(): void
    {
        $this->actingAs($this->creator)
            ->post('/dashboard/verifikasi-identitas', $this->payload(['nik' => '3201']))
            ->assertSessionHasErrors('nik');
    }

    public function test_a_document_that_is_not_an_image_is_refused(): void
    {
        $this->actingAs($this->creator)
            ->post('/dashboard/verifikasi-identitas', $this->payload([
                'id_card' => UploadedFile::fake()->create('ktp.jpg', 20, 'application/x-httpd-php'),
            ]))
            ->assertSessionHasErrors('id_card');

        $this->assertSame(0, IdentityVerification::count());
    }

    public function test_a_pending_submission_cannot_be_resubmitted_over_and_over(): void
    {
        $this->actingAs($this->creator)->post('/dashboard/verifikasi-identitas', $this->payload());

        $this->actingAs($this->creator)
            ->post('/dashboard/verifikasi-identitas', $this->payload())
            ->assertSessionHasErrors('nik');

        $this->assertSame(1, IdentityVerification::count());
    }

    public function test_an_approved_identity_cannot_be_quietly_replaced(): void
    {
        $this->approvedIdentity();

        // Otherwise a stolen session could re-verify as someone else and
        // redirect the payouts.
        $this->actingAs($this->creator)
            ->post('/dashboard/verifikasi-identitas', $this->payload())
            ->assertStatus(422);
    }

    public function test_a_withdrawal_is_refused_until_the_identity_is_approved(): void
    {
        $method = $this->verifiedPayoutMethod();
        $this->fund(200000);

        $this->actingAs($this->creator)
            ->post('/dashboard/penarikan', ['amount' => 100000, 'payout_method_id' => $method->id])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, $this->creator->withdrawals()->count());
    }

    public function test_a_pending_check_is_still_not_a_green_light(): void
    {
        $method = $this->verifiedPayoutMethod();
        $this->fund(200000);
        $this->actingAs($this->creator)->post('/dashboard/verifikasi-identitas', $this->payload());

        $this->actingAs($this->creator)
            ->post('/dashboard/penarikan', ['amount' => 100000, 'payout_method_id' => $method->id])
            ->assertSessionHasErrors('amount');
    }

    public function test_an_approved_creator_can_withdraw(): void
    {
        $method = $this->verifiedPayoutMethod();
        $this->fund(200000);
        $this->approvedIdentity();

        $this->actingAs($this->creator)
            ->post('/dashboard/penarikan', ['amount' => 100000, 'payout_method_id' => $method->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->creator->withdrawals()->count());
    }

    public function test_the_documents_are_not_reachable_by_the_public(): void
    {
        $this->actingAs($this->creator)->post('/dashboard/verifikasi-identitas', $this->payload());
        $row = IdentityVerification::firstOrFail();

        $link = URL::temporarySignedRoute(
            'admin.identity.document',
            now()->addMinutes(10),
            ['verification' => $row->id, 'kind' => 'id_card'],
        );

        // A correctly signed link is not enough; the route is finance-only.
        $this->flushSession();
        $this->get($link)->assertForbidden();

        $this->actingAs($this->creator)->get($link)->assertForbidden();
    }

    public function test_finance_can_open_a_document_only_with_a_signed_link(): void
    {
        $this->actingAs($this->creator)->post('/dashboard/verifikasi-identitas', $this->payload());
        $row = IdentityVerification::firstOrFail();
        $finance = $this->financeAdmin();

        $this->actingAs($finance)
            ->get("/admin/verifikasi-identitas/{$row->id}/dokumen/id_card")
            ->assertForbidden();

        $link = URL::temporarySignedRoute(
            'admin.identity.document',
            now()->addMinutes(10),
            ['verification' => $row->id, 'kind' => 'id_card'],
        );

        $response = $this->actingAs($finance)->get($link)->assertOk();

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_finance_approving_unlocks_payouts_and_leaves_a_trail(): void
    {
        $this->actingAs($this->creator)->post('/dashboard/verifikasi-identitas', $this->payload());
        $row = IdentityVerification::firstOrFail();

        $this->actingAs($this->financeAdmin())
            ->post("/admin/verifikasi-identitas/{$row->id}/setujui")
            ->assertRedirect();

        $this->assertTrue($row->fresh()->isApproved());
        $this->assertDatabaseHas('audit_logs', ['action' => 'identity.approved']);
    }

    public function test_a_rejection_has_to_say_why(): void
    {
        $this->actingAs($this->creator)->post('/dashboard/verifikasi-identitas', $this->payload());
        $row = IdentityVerification::firstOrFail();
        $finance = $this->financeAdmin();

        $this->actingAs($finance)
            ->post("/admin/verifikasi-identitas/{$row->id}/tolak", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($finance)
            ->post("/admin/verifikasi-identitas/{$row->id}/tolak", ['reason' => 'Foto KTP buram, NIK tidak terbaca.'])
            ->assertRedirect();

        $fresh = $row->fresh();
        $this->assertSame(IdentityVerification::REJECTED, $fresh->status);
        // The creator has to be told what to fix, not just that it failed.
        $this->assertSame('Foto KTP buram, NIK tidak terbaca.', $fresh->rejection_reason);
    }

    public function test_a_rejected_creator_may_send_corrected_documents(): void
    {
        $this->actingAs($this->creator)->post('/dashboard/verifikasi-identitas', $this->payload());
        IdentityVerification::firstOrFail()->forceFill([
            'status' => IdentityVerification::REJECTED,
            'rejection_reason' => 'Buram',
        ])->save();

        $this->actingAs($this->creator)
            ->post('/dashboard/verifikasi-identitas', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(IdentityVerification::PENDING, IdentityVerification::firstOrFail()->status);
    }

    public function test_the_full_number_never_reaches_the_creators_own_page(): void
    {
        $this->approvedIdentity();

        $identity = $this->actingAs($this->creator)
            ->get('/dashboard/penarikan')
            ->assertOk()
            ->viewData('page')['props']['identity'];

        $this->assertArrayNotHasKey('nik', $identity);
        $this->assertStringEndsWith('0123', $identity['masked_nik']);
    }

    private function approvedIdentity(): IdentityVerification
    {
        return IdentityVerification::create([
            'user_id' => $this->creator->id,
            'status' => IdentityVerification::APPROVED,
            'full_name' => 'Siti Rahmawati',
            'nik' => '3201234567890123',
            'nik_last4' => '0123',
            'birth_place' => 'Bandung',
            'birth_date' => '1995-04-17',
            'address' => 'Jl. Melati No. 12',
            'id_card_path' => 'kyc/x/ktp.jpg',
            'selfie_path' => 'kyc/x/selfie.jpg',
            'consented_at' => now(),
        ]);
    }

    private function verifiedPayoutMethod(): PayoutMethod
    {
        return PayoutMethod::create([
            'user_id' => $this->creator->id,
            'type' => 'bank',
            'provider' => 'BCA',
            'account_name' => 'Siti Rahmawati',
            'account_number' => '1234567890',
            'account_number_last4' => '7890',
            'status' => 'verified',
            'verified_at' => now(),
            'is_default' => true,
        ]);
    }

    private function fund(float $amount): void
    {
        app(LedgerService::class)->record(
            $this->creator->walletOrCreate(),
            LedgerEntryType::SellerRevenue,
            BalanceBucket::Available,
            $amount,
        );
    }

    private function financeAdmin(): User
    {
        return $this->makeUser([Role::FINANCE_ADMIN]);
    }
}
