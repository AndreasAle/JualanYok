<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The pre-launch check itself.
 *
 * A safety net that quietly passes is worse than none, so each blocker is
 * verified to actually fail the command rather than being assumed to.
 */
class PreflightTest extends TestCase
{
    use RefreshDatabase;

    /** Every setting a production deploy is supposed to have. */
    private function productionConfig(array $overrides = []): void
    {
        config(array_merge([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.url' => 'https://jualanyok.id',
            'session.secure' => true,
            'session.driver' => 'database',
            'cache.default' => 'database',
            'queue.default' => 'database',
            'jualanyok.demo.enabled' => false,
            'payments.default' => 'qris',
            'payments.providers.mock.enabled' => false,
            'payments.providers.qris.enabled' => true,
            'payments.qris.enabled' => false,
            'mail.default' => 'smtp',
            'mail.from.address' => 'halo@jualanyok.id',
            'jualanyok.business.email' => 'halo@jualanyok.id',
            'jualanyok.business.phone' => '+62 812 3456 7890',
            'jualanyok.business.address' => 'Palembang, Sumatera Selatan, Indonesia',
        ], $overrides));

        // The command reads the live environment, not just config.
        app()->detectEnvironment(fn () => config('app.env'));
    }

    public function test_a_properly_configured_deploy_passes(): void
    {
        $this->productionConfig();

        $this->artisan('jualanyok:preflight')->assertSuccessful();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function blockers(): array
    {
        return [
            'debug menyala' => [['app.debug' => true], 'APP_DEBUG'],
            'bukan production' => [['app.env' => 'local'], 'APP_ENV'],
            'tanpa APP_KEY' => [['app.key' => ''], 'APP_KEY'],
            'APP_URL bukan https' => [['app.url' => 'http://jualanyok.id'], 'APP_URL'],
            'cookie tidak aman' => [['session.secure' => false], 'Cookie session'],
            'demo mode menyala' => [['jualanyok.demo.enabled' => true], 'DEMO_MODE'],
            'gateway tiruan menyala' => [['payments.providers.mock.enabled' => true], 'tiruan'],
            'provider default mock' => [['payments.default' => 'mock'], 'PAYMENT_PROVIDER'],
            'mailer masih log' => [['mail.default' => 'log'], 'Mailer'],
            'pengirim email palsu' => [['mail.from.address' => 'hello@example.com'], 'pengirim'],
            'email usaha kosong' => [['jualanyok.business.email' => ''], 'Email usaha'],
            'telepon usaha kosong' => [['jualanyok.business.phone' => ''], 'telepon usaha'],
            'alamat usaha kosong' => [['jualanyok.business.address' => ''], 'Alamat usaha'],
            'queue sinkron' => [['queue.default' => 'sync'], 'Queue'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('blockers')]
    public function test_each_unsafe_setting_stops_the_deploy(array $override, string $expectedMention): void
    {
        $this->productionConfig($override);

        $this->artisan('jualanyok:preflight')
            ->expectsOutputToContain($expectedMention)
            ->assertFailed();
    }

    public function test_an_invalid_qris_code_stops_the_deploy(): void
    {
        // Right shape, wrong checksum — the exact result of forgetting to quote
        // the value in .env, since the payload contains a space.
        $this->productionConfig([
            'payments.qris.enabled' => true,
            'payments.qris.static_payload' => '00020101021126620013ID.CONTOH.WWW0118000000000000000000021200000000000003'
                .'03UMI51440014ID.CO.QRIS.WWW0215ID00000000000000303UMI5204737253033605802ID5911Toko Contoh6007Jakarta61051234563040000',
        ]);

        $this->artisan('jualanyok:preflight')->assertFailed();
    }

    public function test_qris_enabled_without_a_merchant_code_stops_the_deploy(): void
    {
        $this->productionConfig([
            'payments.qris.enabled' => true,
            'payments.qris.static_payload' => '',
        ]);

        $this->artisan('jualanyok:preflight')->assertFailed();
    }

    public function test_ipaymu_enabled_without_live_credentials_stops_the_deploy(): void
    {
        $this->productionConfig([
            'payments.default' => 'ipaymu',
            'payments.providers.qris.enabled' => false,
            'payments.providers.ipaymu.enabled' => true,
            'payments.providers.ipaymu.va' => '',
            'payments.providers.ipaymu.api_key' => '',
            'payments.providers.ipaymu.production' => false,
            'payments.providers.ipaymu.fee_direction' => 'BUYER',
        ]);

        $this->artisan('jualanyok:preflight')
            ->expectsOutputToContain('Kredensial iPaymu')
            ->expectsOutputToContain('mode Live')
            ->expectsOutputToContain('dibebankan ke merchant')
            ->assertFailed();
    }

    public function test_paid_product_files_must_sit_outside_the_public_folder(): void
    {
        $this->productionConfig(['filesystems.disks.local.root' => public_path('files')]);

        $this->artisan('jualanyok:preflight')
            ->expectsOutputToContain('berbayar')
            ->assertFailed();
    }

    public function test_sqlite_is_only_a_warning_not_a_blocker(): void
    {
        // Small deployments genuinely run on SQLite; flag it, do not refuse it.
        // The suite itself runs on SQLite, so this is the live configuration.
        $this->productionConfig();

        $this->artisan('jualanyok:preflight')
            ->expectsOutputToContain('SQLite')
            ->assertSuccessful();
    }
}
