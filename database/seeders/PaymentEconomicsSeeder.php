<?php

namespace Database\Seeders;

use App\Services\MarketplaceLedgerService;
use App\Services\PaymentEconomicsService;
use Illuminate\Database\Seeder;

class PaymentEconomicsSeeder extends Seeder
{
    public function run(): void
    {
        app(PaymentEconomicsService::class)->syncConfiguredRules();
        app(MarketplaceLedgerService::class)->ensureAccounts();
    }
}
