<?php

use App\Models\Store;
use App\Services\AffiliateService;
use App\Services\AnalyticsService;
use App\Services\PaymentService;
use App\Services\PlanService;
use App\Services\WithdrawalService;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled maintenance
|--------------------------------------------------------------------------
| Run with: php artisan schedule:work
*/

// Expire unpaid orders and release the stock they were holding.
Schedule::call(fn () => app(PaymentService::class)->expireStale())
    ->everyTenMinutes()
    ->name('payments:expire-stale')
    ->withoutOverlapping();

// Matures seller revenue and affiliate commission past the refund window.
Schedule::call(fn () => app(WithdrawalService::class)->releaseMaturedRevenue())
    ->hourly()
    ->name('balance:release-revenue')
    ->withoutOverlapping();

Schedule::call(fn () => app(AffiliateService::class)->releaseMatured())
    ->hourly()
    ->name('commissions:release')
    ->withoutOverlapping();

Schedule::call(fn () => app(PlanService::class)->expireLapsed())
    ->daily()
    ->name('subscriptions:expire');

// Rolls yesterday's raw events into the per-day summary the dashboards read.
Schedule::call(function () {
    $analytics = app(AnalyticsService::class);

    Store::query()->chunkById(100, function ($stores) use ($analytics) {
        foreach ($stores as $store) {
            $analytics->aggregate($store, now()->subDay());
            $analytics->aggregate($store, now());
        }
    });
})
    ->hourly()
    ->name('analytics:aggregate')
    ->withoutOverlapping();

Schedule::command('queue:prune-batches --hours=48')->daily();
