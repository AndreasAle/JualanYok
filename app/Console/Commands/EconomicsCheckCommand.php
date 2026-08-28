<?php

namespace App\Console\Commands;

use App\Models\FinancialJournal;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Wallet;
use App\Services\LedgerService;
use App\Support\Money;
use Illuminate\Console\Command;

class EconomicsCheckCommand extends Command
{
    protected $signature = 'jualanyok:economics-check';

    protected $description = 'Rekonsiliasi wallet dan jurnal unit economics marketplace';

    public function handle(LedgerService $ledger): int
    {
        $problems = 0;
        $this->components->info('Memeriksa cached wallet terhadap ledger...');

        Wallet::query()->with('user:id,email')->chunkById(200, function ($wallets) use ($ledger, &$problems) {
            foreach ($wallets as $wallet) {
                $mismatches = $ledger->reconcile($wallet);
                if ($mismatches !== []) {
                    $problems++;
                    $this->components->error("Wallet {$wallet->id} ({$wallet->user?->email}) tidak cocok: ".json_encode($mismatches));
                }
            }
        });

        $this->components->info('Memeriksa keseimbangan jurnal platform...');
        FinancialJournal::query()->with('postings:id,financial_journal_id,direction,amount')->chunkById(200, function ($journals) use (&$problems) {
            foreach ($journals as $journal) {
                $debit = Money::round((float) $journal->postings->where('direction', 'DEBIT')->sum('amount'));
                $credit = Money::round((float) $journal->postings->where('direction', 'CREDIT')->sum('amount'));

                if ($journal->postings->count() < 2 || Money::equals($debit, 0)) {
                    $problems++;
                    $this->components->error("Jurnal {$journal->id} kosong atau tidak punya pasangan posting.");
                } elseif (! Money::equals($debit, $credit)) {
                    $problems++;
                    $this->components->error("Jurnal {$journal->id} tidak seimbang: debit {$debit}, kredit {$credit}.");
                }
            }
        });

        $this->components->info('Memeriksa kelengkapan jurnal order modern...');
        Order::query()
            ->where('settlement_version', '>=', 2)
            ->whereIn('payment_status', ['PAID', 'PARTIALLY_REFUNDED', 'REFUNDED'])
            ->select(['id', 'number'])
            ->chunkById(200, function ($orders) use (&$problems) {
                $journaledOrderIds = FinancialJournal::query()
                    ->where('reference_type', (new Order)->getMorphClass())
                    ->where('event_type', 'ORDER_PAID')
                    ->whereIn('reference_id', $orders->pluck('id'))
                    ->pluck('reference_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                foreach ($orders->whereNotIn('id', $journaledOrderIds) as $order) {
                    $problems++;
                    $this->components->error("Order {$order->number} sudah lunas tetapi jurnal ORDER_PAID tidak ditemukan.");
                }
            });

        $this->components->info('Memeriksa kelengkapan jurnal refund...');
        Refund::query()
            ->where('status', 'COMPLETED')
            ->select(['id'])
            ->chunkById(200, function ($refunds) use (&$problems) {
                $journaledRefundIds = FinancialJournal::query()
                    ->where('reference_type', (new Refund)->getMorphClass())
                    ->where('event_type', 'ORDER_REFUNDED')
                    ->whereIn('reference_id', $refunds->pluck('id'))
                    ->pluck('reference_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                foreach ($refunds->whereNotIn('id', $journaledRefundIds) as $refund) {
                    $problems++;
                    $this->components->error("Refund {$refund->id} selesai tetapi jurnal ORDER_REFUNDED tidak ditemukan.");
                }
            });

        if ($problems > 0) {
            $this->components->error("{$problems} masalah finansial ditemukan. Jangan lakukan payout sebelum direkonsiliasi.");

            return self::FAILURE;
        }

        $this->components->info('Semua wallet, jurnal, settlement order, dan refund konsisten.');

        return self::SUCCESS;
    }
}
