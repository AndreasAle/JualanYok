<?php

use App\Http\Controllers\Creator\AffiliateProgramController;
use App\Http\Controllers\Creator\AnalyticsController;
use App\Http\Controllers\Creator\BalanceController;
use App\Http\Controllers\Creator\BlockController;
use App\Http\Controllers\Creator\ChatController;
use App\Http\Controllers\Creator\CouponController;
use App\Http\Controllers\Creator\CustomerController;
use App\Http\Controllers\Creator\DashboardController;
use App\Http\Controllers\Creator\IdentityVerificationController;
use App\Http\Controllers\Creator\IntegrationController;
use App\Http\Controllers\Creator\LeadController;
use App\Http\Controllers\Creator\MediaController;
use App\Http\Controllers\Creator\OrderController;
use App\Http\Controllers\Creator\PayoutMethodController;
use App\Http\Controllers\Creator\PlanPaymentController;
use App\Http\Controllers\Creator\ProductController;
use App\Http\Controllers\Creator\ProductFileController;
use App\Http\Controllers\Creator\ReviewController;
use App\Http\Controllers\Creator\ReviewReplyController;
use App\Http\Controllers\Creator\ShippingController;
use App\Http\Controllers\Creator\ShippingLabelController;
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

        /* Buyer conversations */
        Route::get('/chat', [ChatController::class, 'index'])->name('chat');
        Route::put('/chat/balasan-otomatis', [ChatController::class, 'autoReply'])->name('chat.auto-reply');
        Route::get('/chat/{conversation}/pesan', [ChatController::class, 'messages'])
            ->middleware('throttle:120,1')
            ->name('chat.messages');
        Route::post('/chat/{conversation}', [ChatController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('chat.store');

        /* Identity check, required before money can leave the platform. */
        Route::post('/verifikasi-identitas', [IdentityVerificationController::class, 'store'])
            ->middleware('throttle:6,60')
            ->name('identity.store');

        /* Reviews. Reading them, and replying — which is all a seller may do. */
        Route::get('/ulasan', [ReviewController::class, 'index'])->name('reviews');
        /* Answering a review. Replying is all a seller may do to one. */
        Route::post('/ulasan/{review}/balas', [ReviewReplyController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('reviews.reply');

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
        Route::patch('/produk/{product}/stok', [ProductController::class, 'updateStock'])
            ->name('products.stock.update');

        /* Deliverables for digital products. Stored privately, never public. */
        Route::prefix('/produk/{product}/files')->name('products.files.')->group(function () {
            Route::post('/', [ProductFileController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('store');
            Route::post('/reorder', [ProductFileController::class, 'reorder'])->name('reorder');
            Route::post('/{file}/replace', [ProductFileController::class, 'replace'])
                ->middleware('throttle:30,1')
                ->name('replace');
            Route::put('/{file}', [ProductFileController::class, 'update'])->name('update');
            Route::delete('/{file}', [ProductFileController::class, 'destroy'])->name('destroy');
        });

        /* Sales */
        Route::get('/pesanan', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/pesanan/{order:number}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/pesanan/{order:number}/kirim', [OrderController::class, 'ship'])->name('orders.ship');
        Route::patch('/pesanan/{order:number}/status-pelacakan', [OrderController::class, 'updateTracking'])->name('orders.tracking.update');
        Route::post('/pesanan/{order:number}/selesai', [OrderController::class, 'complete'])->name('orders.complete');
        Route::post('/pesanan/{order:number}/refund', [OrderController::class, 'requestRefund'])->name('orders.refund');
        Route::post('/pesanan/{order:number}/pesan-kurir', [OrderController::class, 'bookShipment'])->name('orders.shipment.book');
        Route::post('/pesanan/{order:number}/sinkron-kurir', [OrderController::class, 'syncShipment'])->name('orders.shipment.sync');
        Route::get('/pesanan/{order:number}/cetak-resi', ShippingLabelController::class)->name('orders.shipment.label');
        Route::post('/pesanan/{order:number}/respons-komplain', [OrderController::class, 'respondDispute'])->name('orders.dispute.respond');
        Route::get('/pengiriman', [ShippingController::class, 'edit'])->name('shipping.edit');
        Route::put('/pengiriman', [ShippingController::class, 'update'])->name('shipping.update');
        Route::get('/pengiriman/area', [ShippingController::class, 'areas'])->middleware('throttle:30,1')->name('shipping.areas');

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

        /* Plan upgrades through automatic iPaymu or legacy manual QRIS. */
        Route::post('/langganan/bayar', [PlanPaymentController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('subscription.pay.store');
        Route::get('/langganan/bayar/{payment:reference}', [PlanPaymentController::class, 'show'])
            ->name('subscription.pay');
        Route::match(['get', 'post'], '/langganan/bayar/{payment:reference}/cek-status', [PlanPaymentController::class, 'checkStatus'])
            ->middleware('throttle:30,1')
            ->name('subscription.pay.check-status');
        Route::post('/langganan/bayar/{payment:reference}/konfirmasi', [PlanPaymentController::class, 'confirm'])
            ->middleware('throttle:10,1')
            ->name('subscription.pay.confirm');
        Route::delete('/langganan/bayar/{payment:reference}', [PlanPaymentController::class, 'cancel'])
            ->name('subscription.pay.cancel');

        Route::get('/pengaturan', [StoreSettingsController::class, 'index'])->name('settings');
    });
