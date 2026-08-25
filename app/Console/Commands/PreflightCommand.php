<?php

namespace App\Console\Commands;

use App\Support\Qris;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Pre-launch check.
 *
 * Most production incidents in an app like this are not clever — they are a
 * development default that rode along into the live environment. This walks the
 * ones that would actually cost money or leak data, and exits non-zero so a
 * deploy script can stop on them.
 */
class PreflightCommand extends Command
{
    protected $signature = 'jualanyok:preflight';

    protected $description = 'Memeriksa kesiapan aplikasi sebelum dirilis ke produksi';

    /** @var array<int, array{level: string, label: string, detail: string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->components->info('Pemeriksaan kesiapan rilis JualanYok');

        $this->checkEnvironment();
        $this->checkMoney();
        $this->checkStorage();
        $this->checkMail();
        $this->checkBusinessIdentity();
        $this->checkInfrastructure();

        $this->render();

        $failures = collect($this->results)->where('level', 'fail')->count();
        $warnings = collect($this->results)->where('level', 'warn')->count();

        $this->newLine();

        if ($failures > 0) {
            $this->components->error("{$failures} masalah harus dibereskan sebelum rilis.");

            return self::FAILURE;
        }

        if ($warnings > 0) {
            $this->components->warn("Siap rilis, tapi ada {$warnings} hal yang sebaiknya dicek.");

            return self::SUCCESS;
        }

        $this->components->info('Semua pemeriksaan lolos.');

        return self::SUCCESS;
    }

    private function checkEnvironment(): void
    {
        $this->assert(
            app()->environment('production'),
            'APP_ENV = production',
            'Sekarang "'.app()->environment().'". Di luar production, mode ketat menyala dan sebagian halaman balas 500.',
        );

        $this->assert(
            ! (bool) config('app.debug'),
            'APP_DEBUG mati',
            'Debug menyala berarti stack trace dan isi konfigurasi tampil ke pengunjung saat error.',
        );

        $this->assert(
            filled(config('app.key')),
            'APP_KEY terisi',
            'Tanpa APP_KEY, session dan data terenkripsi tidak aman.',
        );

        $this->assert(
            str_starts_with((string) config('app.url'), 'https://'),
            'APP_URL memakai https',
            'APP_URL sekarang "'.config('app.url').'". Tautan download bertanda tangan ikut menandatangani host, jadi ini harus persis sama dengan alamat yang melayani permintaan.',
        );

        $this->assert(
            (bool) config('session.secure'),
            'Cookie session hanya lewat HTTPS',
            'SESSION_SECURE_COOKIE=false membuat cookie login bisa terkirim lewat koneksi polos.',
        );
    }

    private function checkMoney(): void
    {
        $this->assert(
            ! (bool) config('jualanyok.demo.enabled'),
            'DEMO_MODE mati',
            'Selama menyala, tombol "Simulasi Bayar Sukses" aktif — siapa pun bisa menandai pesanan lunas tanpa membayar.',
        );

        $this->assert(
            ! (bool) config('payments.providers.mock.enabled'),
            'Gateway tiruan mati',
            'Provider mock menyelesaikan pembayaran tanpa uang sungguhan.',
        );

        $this->assert(
            config('payments.default') !== 'mock',
            'PAYMENT_PROVIDER bukan mock',
            'Provider default masih "mock".',
        );

        $liveProviders = collect(config('payments.providers', []))
            ->reject(fn ($config, $key) => $key === 'mock')
            ->filter(fn ($config) => (bool) ($config['enabled'] ?? false))
            ->keys();

        $this->assert(
            $liveProviders->isNotEmpty(),
            'Ada metode pembayaran sungguhan yang aktif',
            'Tidak ada provider non-mock yang menyala, jadi pembeli tidak punya cara membayar.',
        );

        $this->checkQris();
        $this->checkIpaymu();
    }

    private function checkIpaymu(): void
    {
        $config = config('payments.providers.ipaymu', []);

        if (! (bool) ($config['enabled'] ?? false)) {
            $this->skip('iPaymu mati', 'Tidak dipakai, jadi kredensialnya tidak diperiksa.');

            return;
        }

        $this->assert(
            filled($config['va'] ?? null) && filled($config['api_key'] ?? null),
            'Kredensial iPaymu terisi',
            'Isi IPAYMU_VA dan IPAYMU_API_KEY di .env server. Jangan simpan nilainya di repository.',
        );

        $this->assert(
            (bool) ($config['production'] ?? false),
            'iPaymu memakai mode Live',
            'IPAYMU_PRODUCTION=false masih mengarah ke Sandbox.',
        );

        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        $this->assert(
            $host !== '' && ! in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)
                && ! str_ends_with($host, '.test') && ! str_ends_with($host, '.local'),
            'APP_URL bisa dijangkau iPaymu',
            'APP_URL "'.config('app.url').'" tidak bisa dihubungi dari internet. '
                .'iPaymu tidak bisa mengirim callback ke sana, dan tagihannya ditolak pemeriksaan risiko mereka.',
        );

        $this->assert(
            strtoupper((string) ($config['fee_direction'] ?? '')) === 'MERCHANT',
            'Biaya iPaymu dibebankan ke merchant',
            'Gunakan IPAYMU_FEE_DIRECTION=MERCHANT agar nominal callback sama persis dengan tagihan pembeli.',
        );
    }

    private function checkQris(): void
    {
        $enabledAnywhere = (bool) config('payments.qris.enabled')
            || (bool) config('payments.providers.qris.enabled');

        if (! $enabledAnywhere) {
            $this->skip('QRIS mati', 'Tidak dipakai, jadi tidak diperiksa.');

            return;
        }

        $payload = trim((string) config('payments.qris.static_payload'));

        if ($payload === '') {
            $this->recordFailure('Kode QRIS merchant terisi', 'QRIS menyala tapi QRIS_STATIC_PAYLOAD kosong.');

            return;
        }

        $this->assert(
            Qris::looksValid($payload),
            'Kode QRIS merchant valid',
            'Checksum QRIS_STATIC_PAYLOAD tidak cocok. Kemungkinan terpotong, atau lupa diapit tanda kutip di .env karena memuat spasi.',
        );

        if (Qris::looksValid($payload)) {
            $this->note('Pembayaran QRIS masuk ke: '.(Qris::merchantName($payload) ?? 'nama merchant tidak terbaca'));
        }
    }

    private function checkStorage(): void
    {
        // file_exists() rather than is_link(): it also recognises a Windows
        // junction and a plain directory, both of which serve just as well.
        $this->assert(
            file_exists(public_path('storage')),
            'Symlink storage publik ada',
            'Tanpa `php artisan storage:link`, semua gambar toko balas 403.',
        );

        // Paid product files must not sit anywhere the web server can serve.
        $privateRoot = (string) config('filesystems.disks.local.root');

        $this->assert(
            ! str_starts_with($privateRoot, public_path()),
            'File produk berbayar di luar folder publik',
            'Disk "local" mengarah ke dalam public/, artinya file yang dijual bisa diunduh tanpa membayar.',
        );

        try {
            Storage::disk('local')->put('preflight.txt', 'ok');
            Storage::disk('local')->delete('preflight.txt');
            $writable = true;
        } catch (Throwable) {
            $writable = false;
        }

        $this->assert($writable, 'Penyimpanan privat bisa ditulis', 'Upload file produk akan gagal.');
    }

    private function checkMail(): void
    {
        $mailer = config('mail.default');

        $this->assert(
            $mailer !== 'log' && $mailer !== 'array',
            'Mailer sungguhan terkonfigurasi',
            'Mailer "'.$mailer.'" cuma menulis ke log. Verifikasi email, reset password, dan struk tidak akan sampai ke pembeli.',
        );

        $from = (string) config('mail.from.address');

        $this->assert(
            $from !== '' && ! str_contains($from, 'example.com') && ! str_contains($from, '.test'),
            'Alamat pengirim email sudah asli',
            'MAIL_FROM_ADDRESS masih "'.$from.'".',
        );
    }

    private function checkBusinessIdentity(): void
    {
        foreach ([
            'email' => 'Email usaha publik terisi',
            'phone' => 'Nomor telepon usaha publik terisi',
            'address' => 'Alamat usaha publik terisi',
        ] as $key => $label) {
            $this->assert(
                filled(config("jualanyok.business.{$key}")),
                $label,
                'Isi BUSINESS_'.strtoupper($key).' di .env agar identitas pada halaman Kontak dan footer sesuai data merchant.',
            );
        }
    }

    private function checkInfrastructure(): void
    {
        $this->assert(
            config('queue.default') !== 'sync',
            'Queue berjalan di latar belakang',
            'Dengan "sync", pengiriman struk dan fulfilment berjalan di dalam request pembayaran — lambat, dan satu error bisa menggagalkan pembayaran yang sudah lunas.',
        );

        $driver = config('database.default');

        $this->warnIf(
            $driver === 'sqlite',
            'Database bukan SQLite',
            'SQLite jalan, tapi mengunci satu penulis pada satu waktu. Untuk trafik sungguhan pakai MySQL 8.',
        );

        $this->warnIf(
            config('cache.default') === 'file' || config('session.driver') === 'file',
            'Cache dan session tidak memakai file',
            'Driver file tidak bisa dibagi antar server dan lambat saat ramai.',
        );
    }

    /* ---------------------------------------------------------------------- */

    private function assert(?bool $passed, string $label, string $detail): void
    {
        $this->results[] = [
            'level' => $passed === true ? 'pass' : 'fail',
            'label' => $label,
            'detail' => $passed ? '' : $detail,
        ];
    }

    private function warnIf(bool $problem, string $label, string $detail): void
    {
        $this->results[] = [
            'level' => $problem ? 'warn' : 'pass',
            'label' => $label,
            'detail' => $problem ? $detail : '',
        ];
    }

    private function recordFailure(string $label, string $detail): void
    {
        $this->results[] = ['level' => 'fail', 'label' => $label, 'detail' => $detail];
    }

    private function skip(string $label, string $detail): void
    {
        $this->results[] = ['level' => 'skip', 'label' => $label, 'detail' => $detail];
    }

    private function note(string $text): void
    {
        $this->results[] = ['level' => 'note', 'label' => $text, 'detail' => ''];
    }

    private function render(): void
    {
        $this->newLine();

        foreach ($this->results as $result) {
            $mark = match ($result['level']) {
                'pass' => '<fg=green>  OK  </>',
                'fail' => '<fg=red;options=bold> GAGAL</>',
                'warn' => '<fg=yellow> CEK  </>',
                'skip' => '<fg=gray> LEWAT</>',
                default => '<fg=blue> INFO </>',
            };

            $this->line("{$mark}  {$result['label']}");

            if ($result['detail'] !== '') {
                $this->line("        <fg=gray>{$result['detail']}</>");
            }
        }
    }
}
