<?php

namespace App\Console\Commands;

use App\Services\ShippingService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

class ShippingCheckCommand extends Command
{
    protected $signature = 'jualanyok:shipping-check {query=Palembang : Wilayah yang dipakai untuk tes pencarian}';

    protected $description = 'Menguji koneksi provider pengiriman tanpa menampilkan token API';

    public function handle(ShippingService $shipping): int
    {
        $provider = (string) config('shipping.default', 'manual');
        $query = trim((string) $this->argument('query'));

        $this->info("Menguji provider pengiriman [{$provider}] dengan pencarian [{$query}]...");

        try {
            $areas = $shipping->searchAreas($query);
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            $message = data_get($exception->response->json(), 'message')
                ?? data_get($exception->response->json(), 'error')
                ?? 'Respons provider tidak memiliki pesan.';

            $this->error("Provider menjawab HTTP {$status}: {$message}");

            if (in_array($status, [401, 403], true)) {
                $this->warn('Buat token API Biteship baru, perbarui BITESHIP_API_TOKEN, lalu jalankan php artisan optimize:clear.');
            }

            return self::FAILURE;
        } catch (ConnectionException $exception) {
            $this->error('Server tidak dapat terhubung ke provider: '.$exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception::class.': '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($areas === []) {
            $this->warn('Koneksi berhasil, tetapi provider tidak menemukan wilayah tersebut.');

            return self::SUCCESS;
        }

        $this->info('Koneksi berhasil. Contoh hasil:');
        $this->table(
            ['Area ID', 'Wilayah', 'Kode pos'],
            collect($areas)->take(5)->map(fn (array $area) => [
                $area['id'] ?? '-',
                $area['name'] ?? '-',
                $area['postal_code'] ?? '-',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
