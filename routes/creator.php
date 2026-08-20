<?php

use App\Http\Controllers\Creator\AffiliateProgramController;
use App\Http\Controllers\Creator\AnalyticsController;
use App\Http\Controllers\Creator\BalanceController;
use App\Http\Controllers\Creator\BlockController;
use App\Http\Controllers\Creator\CouponController;
use App\Http\Controllers\Creator\CustomerController;
use App\Http\Controllers\Creator\DashboardController;
use App\Http\Controllers\Creator\IntegrationController;
use App\Http\Controllers\Creator\LeadController;
use App\Http\Controllers\Creator\MediaController;
use App\Http\Controllers\Creator\OrderController;
use App\Http\Controllers\Creator\PayoutMethodController;
use App\Http\Controllers\Creator\ProductController;
use App\Http\Controllers\Creator\StoreBuilderController;
use App\Http\Controllers\Creator\StoreSettingsController;
use App\Http\Controllers\Creator\SubscriptionController;
use App\Http\Controllers\Creator\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'creator'])
    ->prefix('dashboard')
    ->name('creator.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        /* Storefront builder */
        Route::get('/toko', [StoreBuilderController::class, 'edit'])->name('builder');
        Route::put('/toko', [StoreSettingsController::class, 'update'])->name('store.update');
        Route::post('/toko/publish', [StoreBuilderController::class, 'publish'])->name('store.publish');
        Route::post('/toko/unpublish', [StoreBuilderController::class, 'unpublish'])->name('store.unpublish');
        Route::post('/toko/template/{template:slug}', [StoreBuilderController::class, 'applyTemplate'])
            ->name('store.template');
        Route::put('/toko/tema', [StoreSettingsController::class, 'updateTheme'])->name('store.theme');

        Route::post('/blocks', [BlockController::class, 'store'])->name('blocks.store');
        Route::put('/blocks/{block}', [BlockController::class, 'update'])->name('blocks.update');
        Route::post('/blocks/{block}/duplicate', [BlockController::class, 'duplicate'])->name('blocks.duplicate');
        Route::delete('/blocks/{block}', [BlockController::class, 'destroy'])->name('blocks.destroy');
        Route::post('/blocks/reorder', [BlockController::class, 'reorder'])->name('blocks.reorder');

        /* Shared image uploads used by image and gallery blocks */
        Route::post('/media', [MediaController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('media.store');
        Route::delete('/media', [MediaController::class, 'destroy'])->name('media.destroy');

        /* Catalogue */
        Route::resource('produk', ProductController::class)
            ->parameters(['produk' => 'product'])
            ->names('products');
        Route::post('/produk/{product}/duplicate', [ProductController::class, 'duplicate'])
            ->name('products.duplicate');

        /* Sales */
        Route::get('/pesanan', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/pesanan/{order:number}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/pesanan/{order:number}/kirim', [OrderController::class, 'ship'])->name('orders.ship');
        Route::post('/pesanan/{order:number}/selesai', [OrderController::class, 'complete'])->name('orders.complete');
        Route::post('/pesanan/{order:number}/refund', [OrderController::class, 'requestRefund'])->name('orders.refund');

        Route::get('/pelanggan', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/pelanggan/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/pelanggan/export', [CustomerController::class, 'export'])->name('customers.export');

        Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('/leads/export', [LeadController::class, 'export'])->name('leads.export');

        /* Marketing */
        Route::resource('kupon', CouponController::class)
            ->parameters(['kupon' => 'coupon'])
            ->names('coupons')
            ->except('show');

        Route::get('/integrasi', [IntegrationController::class, 'index'])->name('integrations.index');
        Route::put('/integrasi/pixels', [IntegrationController::class, 'updatePixels'])->name('integrations.pixels');
        Route::post('/integrasi/webhooks', [IntegrationController::class, 'storeWebhook'])->name('integrations.webhooks.store');
        Route::delete('/integrasi/webhooks/{endpoint}', [IntegrationController::class, 'destroyWebhook'])
            ->name('integrations.webhooks.destroy');
        Route::post('/integrasi/webhooks/{endpoint}/test', [IntegrationController::class, 'testWebhook'])
            ->name('integrations.webhooks.test');

        /* Affiliate program (seller side) */
        Route::get('/affiliate', [AffiliateProgramController::class, 'index'])->name('affiliate.index');
        Route::put('/affiliate', [AffiliateProgramController::class, 'update'])->name('affiliate.update');
        Route::post('/affiliate/aplikasi/{application}/review', [AffiliateProgramController::class, 'review'])
            ->name('affiliate.review');

        /* Money */
        Route::get('/saldo', [BalanceController::class, 'index'])->name('balance');
        Route::get('/penarikan', [WithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::post('/penarikan', [WithdrawalController::class, 'store'])->name('withdrawals.store');
        Route::post('/penarikan/{withdrawal:number}/batal', [WithdrawalController::class, 'cancel'])
            ->name('withdrawals.cancel');

        Route::post('/rekening', [PayoutMethodController::class, 'store'])->name('payout-methods.store');
        Route::delete('/rekening/{payoutMethod}', [PayoutMethodController::class, 'destroy'])
            ->name('payout-methods.destroy');

        /* Insights & account */
        Route::get('/analitik', [AnalyticsController::class, 'index'])->name('analytics');
        Route::get('/langganan', [SubscriptionController::class, 'index'])->name('subscription');
        Route::post('/langganan', [SubscriptionController::class, 'subscribe'])->name('subscription.subscribe');
        Route::post('/langganan/batal', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');

        Route::get('/pengaturan', [StoreSettingsController::class, 'index'])->name('settings');
    });
