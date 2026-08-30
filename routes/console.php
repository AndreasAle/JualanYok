<?php

use App\Models\Store;
use App\Services\AffiliateService;
use App\Services\AnalyticsService;
use App\Services\FulfillmentService;
use App\Services\PaymentService;
use App\Services\PlanPaymentService;
use App\Services\PlanService;
use App\Services\ShippingService;
use App\Services\WithdrawalService;
use Illuminate\Support\Facades\DB;
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

Schedule::call(fn () => app(WithdrawalService::class)->releaseMaturedReserves())
    ->dailyAt('02:20')
    ->name('release-matured-reserves')
    ->withoutOverlapping();

// Courier callbacks are primary; polling closes gaps caused by delayed webhooks.
Schedule::call(fn () => app(ShippingService::class)->syncActive())
    ->everyTenMinutes()
    ->name('shipping:sync-active')
    ->withoutOverlapping();

// Physical-order escrow closes after the complaint window when buyers stay silent.
Schedule::call(fn () => app(FulfillmentService::class)->autoCompleteDelivered())
    ->hourly()
    ->name('orders:auto-complete-delivered')
    ->withoutOverlapping();

Schedule::call(fn () => app(AffiliateService::class)->releaseMatured())
    ->hourly()
    ->name('commissions:release')
    ->withoutOverlapping();

Schedule::call(fn () => app(PlanService::class)->expireLapsed())
    ->daily()
    ->name('subscriptions:expire');

/*
 * Releases the unique amounts held by abandoned plan payments. Without this the
 * figures are only freed when an admin happens to open the queue, so a quiet
 * week would leave dozens of rupiah amounts reserved for nobody.
 */
Schedule::call(fn () => app(PlanPaymentService::class)->expireLapsed())
    ->everyTenMinutes()
    ->name('plan-payments:expire-stale')
    ->withoutOverlapping();

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

Schedule::command('jualanyok:notification-digests')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

Schedule::command('jualanyok:notification-reminders')
    ->dailyAt('07:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

Schedule::call(function () {
    $days = max(30, (int) config('notifications.retention_days', 90));
    DB::table('notifications')->whereNotNull('archived_at')->where('created_at', '<', now()->subDays($days))->delete();
})->dailyAt('02:45')->name('notifications:prune-archives')->withoutOverlapping();
