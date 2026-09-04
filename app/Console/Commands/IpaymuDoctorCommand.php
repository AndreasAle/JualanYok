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

    private function attempt(array $config, User $user, string $phone): int
    {
        $amount = max(1000, (int) $this->option('amount'));
        $reference = 'DOCTOR-'.now()->format('YmdHis');

        $payload = [
            'name' => $user->name,
            'phone' => $phone,
            'email' => $user->email,
            'amount' => $amount,
            'notifyUrl' => route('webhooks.payments', ['provider' => 'ipaymu']),
            'referenceId' => $reference,
            'paymentMethod' => 'qris',
            'paymentChannel' => 'mpm',
            'comments' => 'Uji diagnosa JualanYok',
            'feeDirection' => 'MERCHANT',
            'returnUrl' => config('app.url'),
            'cancelUrl' => config('app.url'),
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $va = trim((string) $config['va']);
        $apiKey = trim((string) $config['api_key']);

        $signature = hash_hmac(
            'sha256',
            'POST:'.$va.':'.strtolower(hash('sha256', (string) $body)).':'.$apiKey,
            $apiKey,
        );

        $base = ($config['production'] ?? false)
            ? 'https://my.ipaymu.com/api/v2'
            : 'https://sandbox.ipaymu.com/api/v2';

        $this->newLine();
        $this->components->info("Mengirim satu tagihan uji Rp {$amount} ke {$base}");

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'va' => $va,
                'signature' => $signature,
                'timestamp' => now()->format('YmdHis'),
            ])->withBody((string) $body, 'application/json')
                ->timeout(20)
                ->post($base.'/payment/direct');

            $this->newLine();
            $this->components->info('Balasan mentah iPaymu (HTTP '.$response->status().')');
            $this->line($response->body());

            if ($response->successful()) {
                $this->newLine();
                $this->components->info('Tagihan uji berhasil dibuat. Konfigurasinya sehat.');

                return self::SUCCESS;
            }

            $this->newLine();
            $this->components->error('iPaymu menolak. Pesan di atas adalah alasan sebenarnya — kirimkan itu ke support iPaymu kalau tidak jelas.');

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->components->error('Gagal menghubungi iPaymu: '.$e->getMessage());

            return self::FAILURE;
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
