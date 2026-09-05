<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDisputeController;
use App\Http\Controllers\Admin\AdminEconomicsController;
use App\Http\Controllers\Admin\AdminIdentityVerificationController;
use App\Http\Controllers\Admin\AdminMarketplaceController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminPayoutMethodController;
use App\Http\Controllers\Admin\AdminRefundController;
use App\Http\Controllers\Admin\AdminStoreController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminWithdrawalController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\LedgerController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PlanPaymentController;
use App\Http\Controllers\Admin\PlatformSettingController;
use App\Http\Controllers\Admin\QrisPaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/ekonomi', [AdminEconomicsController::class, 'index'])->name('economics.index');

        Route::middleware('admin:support-admin,super-admin')->group(function () {
            Route::get('/marketplace', [AdminMarketplaceController::class, 'index'])->name('marketplace.index');
            Route::post('/marketplace/produk/{product}/moderasi', [AdminMarketplaceController::class, 'moderate'])->name('marketplace.moderate');
            Route::post('/marketplace/produk/{product}/unggulkan', [AdminMarketplaceController::class, 'feature'])->name('marketplace.feature');
        });

        Route::get('/pengguna', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/pengguna/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('/pengguna/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::post('/pengguna/{user}/aktifkan', [AdminUserController::class, 'reinstate'])->name('users.reinstate');
        Route::post('/pengguna/{user}/impersonate', [AdminUserController::class, 'impersonate'])
            ->middleware('admin:super-admin')
            ->name('users.impersonate');

        Route::get('/toko', [AdminStoreController::class, 'index'])->name('stores.index');
        Route::post('/toko/{store}/suspend', [AdminStoreController::class, 'suspend'])->name('stores.suspend');
        Route::post('/toko/{store}/aktifkan', [AdminStoreController::class, 'reinstate'])->name('stores.reinstate');

        Route::get('/pesanan', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/pesanan/{order:number}', [AdminOrderController::class, 'show'])->name('orders.show');

        Route::get('/refund', [AdminRefundController::class, 'index'])->name('refunds.index');
        Route::get('/komplain', [AdminDisputeController::class, 'index'])->name('disputes.index');
        Route::post('/komplain/{dispute}/putuskan', [AdminDisputeController::class, 'resolve'])->name('disputes.resolve');
        Route::post('/refund/{refund}/setujui', [AdminRefundController::class, 'approve'])->name('refunds.approve');
        Route::post('/refund/{refund}/selesaikan', [AdminRefundController::class, 'complete'])->name('refunds.complete');
        Route::post('/refund/{refund}/tolak', [AdminRefundController::class, 'reject'])->name('refunds.reject');

        Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger.index');

        Route::get('/penarikan', [AdminWithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::post('/penarikan/{withdrawal:number}/setujui', [AdminWithdrawalController::class, 'approve'])
            ->name('withdrawals.approve');
        Route::post('/penarikan/{withdrawal:number}/tolak', [AdminWithdrawalController::class, 'reject'])
            ->name('withdrawals.reject');
        Route::post('/penarikan/{withdrawal:number}/bayar', [AdminWithdrawalController::class, 'markPaid'])
            ->name('withdrawals.paid');

        Route::middleware('admin:finance-admin,super-admin')->group(function () {
            Route::get('/rekening-pencairan', [AdminPayoutMethodController::class, 'index'])
                ->name('payout-methods.index');
            Route::post('/rekening-pencairan/{payoutMethod}/setujui', [AdminPayoutMethodController::class, 'approve'])
                ->name('payout-methods.approve');
            Route::post('/rekening-pencairan/{payoutMethod}/tolak', [AdminPayoutMethodController::class, 'reject'])
                ->name('payout-methods.reject');

            /*
             * Identity checks. Finance only, and the two photographs are served
             * from a private disk behind a short-lived signed link.
             */
            Route::get('/verifikasi-identitas', [AdminIdentityVerificationController::class, 'index'])
                ->name('identity.index');
            Route::get('/verifikasi-identitas/{verification}/dokumen/{kind}', [AdminIdentityVerificationController::class, 'document'])
                ->name('identity.document');
            Route::post('/verifikasi-identitas/{verification}/setujui', [AdminIdentityVerificationController::class, 'approve'])
                ->name('identity.approve');
            Route::post('/verifikasi-identitas/{verification}/tolak', [AdminIdentityVerificationController::class, 'reject'])
                ->name('identity.reject');
        });

        Route::get('/paket', [PlanController::class, 'index'])->name('plans.index');
        Route::put('/paket/{plan:slug}', [PlanController::class, 'update'])->name('plans.update');

        /* Manual QRIS subscription payments awaiting confirmation. */
        Route::get('/pembayaran-langganan', [PlanPaymentController::class, 'index'])
            ->name('plan-payments.index');
        Route::post('/pembayaran-langganan/{payment:reference}/setujui', [PlanPaymentController::class, 'approve'])
            ->name('plan-payments.approve');
        Route::post('/pembayaran-langganan/{payment:reference}/tolak', [PlanPaymentController::class, 'reject'])
            ->name('plan-payments.reject');

        /* Manual QRIS payments for customer orders. */
        Route::get('/pembayaran-qris', [QrisPaymentController::class, 'index'])
            ->name('qris-payments.index');
        Route::post('/pembayaran-qris/{payment}/setujui', [QrisPaymentController::class, 'approve'])
            ->name('qris-payments.approve');
        Route::post('/pembayaran-qris/{payment}/tolak', [QrisPaymentController::class, 'reject'])
            ->name('qris-payments.reject');

        Route::get('/pengaturan', [PlatformSettingController::class, 'index'])->name('settings.index');
        Route::put('/pengaturan', [PlatformSettingController::class, 'update'])->name('settings.update');

        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
    });

Route::post('/admin/stop-impersonate', [AdminUserController::class, 'stopImpersonating'])
    ->middleware('auth')
    ->name('admin.stop-impersonate');
