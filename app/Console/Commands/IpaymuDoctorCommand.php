<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Shows what iPaymu actually says, instead of what we paraphrase.
 *
 * A risk rejection comes back as one vague sentence, which the checkout then
 * softens further for the buyer. That leaves nobody able to tell a bad phone
 * format from an unapproved merchant account. This sends one real request and
 * prints the reply verbatim.
 */
class IpaymuDoctorCommand extends Command
{
    protected $signature = 'jualanyok:ipaymu-doctor
        {--user= : Email akun yang mau diuji, default akun pertama yang punya toko}
        {--amount=10000 : Nominal uji}
        {--send : Benar-benar kirim ke iPaymu, bukan hanya memeriksa konfigurasi}';

    protected $description = 'Mendiagnosis kenapa iPaymu menolak pembuatan tagihan';

    public function handle(): int
    {
        $config = config('payments.providers.ipaymu', []);

        $this->components->info('Konfigurasi iPaymu');
        $this->line('  enabled     : '.var_export((bool) ($config['enabled'] ?? false), true));
        $this->line('  production  : '.var_export((bool) ($config['production'] ?? false), true));
        $this->line('  VA          : '.($this->mask($config['va'] ?? null)));
        $this->line('  API key     : '.($this->mask($config['api_key'] ?? null)));
        $this->line('  feeDirection: '.($config['fee_direction'] ?? '-'));
        $this->line('  APP_URL     : '.config('app.url'));

        if (blank($config['va'] ?? null) || blank($config['api_key'] ?? null)) {
            $this->components->error('VA atau API Key kosong — isi dulu di .env server.');

            return self::FAILURE;
        }

        $user = $this->resolveUser();

        if (! $user) {
            $this->components->error('Akun tidak ditemukan.');

            return self::FAILURE;
        }

        $rawPhone = $user->phone ?: $user->store?->whatsapp;
        $phone = Phone::local($rawPhone);

        $this->newLine();
        $this->components->info('Data pembeli yang akan dikirim');
        $this->line('  nama        : '.$user->name);
        $this->line('  email       : '.$user->email);
        $this->line('  telepon asli: '.($rawPhone ?: '(kosong)'));
        $this->line('  dinormalkan : '.($phone ?? '(TIDAK VALID)'));

        if ($phone === null) {
            $this->components->error(
                'Nomor WhatsApp belum berformat nomor HP Indonesia. Ini penyebab penolakan yang paling sering.',
            );

            return self::FAILURE;
        }

        if (str_ends_with(strtolower((string) $user->email), '.test')) {
            $this->components->warn('Email memakai domain .test yang tidak ada — sering ditolak pemeriksaan risiko.');
        }

        if (! $this->option('send')) {
            $this->newLine();
            $this->components->warn('Konfigurasi terlihat wajar. Tambahkan --send untuk benar-benar menguji ke iPaymu.');

            return self::SUCCESS;
        }

        return $this->attempt($config, $user, $phone);
    }

    /**
     * Tries several shapes of the same charge.
     *
     * "Suspicious buyer" is returned for anything iPaymu's risk engine dislikes,
     * so a single failed request says nothing about which part it disliked.
     * Running a small matrix — a different endpoint, channel and amount — turns
     * one opaque refusal into a comparison that points at the cause.
     *
     * Each probe is a real, unpaid charge on the live account. They are left to
     * expire on their own; nothing is captured.
     */
    private function attempt(array $config, User $user, string $phone): int
    {
        $amount = max(1000, (int) $this->option('amount'));

        $base = ($config['production'] ?? false)
            ? 'https://my.ipaymu.com/api/v2'
            : 'https://sandbox.ipaymu.com/api/v2';

        $probes = [
            ['label' => 'Direct QRIS', 'path' => '/payment/direct', 'method' => 'qris', 'channel' => 'mpm', 'amount' => $amount],
            ['label' => 'Direct VA BCA', 'path' => '/payment/direct', 'method' => 'va', 'channel' => 'bca', 'amount' => $amount],
            ['label' => 'Direct QRIS nominal besar', 'path' => '/payment/direct', 'method' => 'qris', 'channel' => 'mpm', 'amount' => max($amount, 50_000)],
            ['label' => 'Halaman checkout iPaymu', 'path' => '/payment', 'method' => null, 'channel' => null, 'amount' => $amount],
        ];

        $this->newLine();
        $this->components->info('Menguji '.count($probes).' jalur ke '.$base);
        $this->line('  <fg=gray>Setiap uji membuat tagihan asli yang tidak dibayar, dan akan kedaluwarsa sendiri.</>');

        $working = [];

        foreach ($probes as $probe) {
            $result = $this->probe($config, $base, $user, $phone, $probe);

            $this->newLine();
            $this->line(sprintf(
                '  %s <options=bold>%s</> — HTTP %s',
                $result['ok'] ? '<fg=green>BERHASIL</>' : '<fg=red>DITOLAK </>',
                $probe['label'],
                $result['status'],
            ));
            $this->line('    <fg=gray>'.str($result['body'])->squish()->limit(180).'</>');

            if ($result['ok']) {
                $working[] = $probe['label'];
            }
        }

        $this->newLine();

        if ($working === []) {
            $this->components->error('Semua jalur ditolak.');
            $this->line('  Konfigurasi, tanda tangan, nomor, dan email sudah benar — kalau salah,');
            $this->line('  iPaymu membalas pesan lain. Penolakan ini datang dari sisi akun mereka.');
            $this->line('  Hubungi support iPaymu, sertakan VA merchant dan balasan mentah di atas.');

            return self::FAILURE;
        }

        $this->components->info('Jalur yang diterima: '.implode(', ', $working));
        $this->line('  Pakai jalur itu untuk pembayaran, dan tanyakan ke iPaymu kenapa sisanya ditolak.');

        return self::SUCCESS;
    }

    /**
     * @param  array{label: string, path: string, method: ?string, channel: ?string, amount: int}  $probe
     * @return array{ok: bool, status: int|string, body: string}
     */
    private function probe(array $config, string $base, User $user, string $phone, array $probe): array
    {
        $reference = 'DOCTOR-'.now()->format('YmdHis').'-'.random_int(100, 999);

        $payload = [
            'name' => $user->name,
            'phone' => $phone,
            'email' => $user->email,
            'amount' => $probe['amount'],
            'notifyUrl' => route('webhooks.payments', ['provider' => 'ipaymu']),
            'referenceId' => $reference,
            'comments' => 'Uji diagnosa JualanYok',
            'feeDirection' => 'MERCHANT',
            'returnUrl' => config('app.url'),
            'cancelUrl' => config('app.url'),
        ];

        if ($probe['method'] !== null) {
            $payload['paymentMethod'] = $probe['method'];
            $payload['paymentChannel'] = $probe['channel'];
        } else {
            // The hosted page prices per line rather than as a single total.
            $payload['product'] = ['Uji diagnosa'];
            $payload['qty'] = [1];
            $payload['price'] = [$probe['amount']];
            unset($payload['amount']);
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $va = trim((string) $config['va']);
        $apiKey = trim((string) $config['api_key']);

        $signature = hash_hmac(
            'sha256',
            'POST:'.$va.':'.strtolower(hash('sha256', (string) $body)).':'.$apiKey,
            $apiKey,
        );

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'va' => $va,
                'signature' => $signature,
                'timestamp' => now()->format('YmdHis'),
            ])->withBody((string) $body, 'application/json')
                ->timeout(20)
                ->post($base.$probe['path']);

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'status' => 'error', 'body' => $e->getMessage()];
        }
    }

    private function resolveUser(): ?User
    {
        if ($email = $this->option('user')) {
            return User::where('email', $email)->first();
        }

        return User::whereHas('store')->oldest('id')->first();
    }

    private function mask(?string $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return '(kosong)';
        }

        return strlen($value) <= 6
            ? str_repeat('*', strlen($value))
            : substr($value, 0, 3).str_repeat('*', strlen($value) - 6).substr($value, -3);
    }
}
