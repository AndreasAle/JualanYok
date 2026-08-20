<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminRefundController;
use App\Http\Controllers\Admin\AdminStoreController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminWithdrawalController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\LedgerController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PlatformSettingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

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
        Route::post('/refund/{refund}/setujui', [AdminRefundController::class, 'approve'])->name('refunds.approve');
        Route::post('/refund/{refund}/tolak', [AdminRefundController::class, 'reject'])->name('refunds.reject');

        Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger.index');

        Route::get('/penarikan', [AdminWithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::post('/penarikan/{withdrawal:number}/setujui', [AdminWithdrawalController::class, 'approve'])
            ->name('withdrawals.approve');
        Route::post('/penarikan/{withdrawal:number}/tolak', [AdminWithdrawalController::class, 'reject'])
            ->name('withdrawals.reject');
        Route::post('/penarikan/{withdrawal:number}/bayar', [AdminWithdrawalController::class, 'markPaid'])
            ->name('withdrawals.paid');

        Route::get('/paket', [PlanController::class, 'index'])->name('plans.index');
        Route::put('/paket/{plan:slug}', [PlanController::class, 'update'])->name('plans.update');

        Route::get('/pengaturan', [PlatformSettingController::class, 'index'])->name('settings.index');
        Route::put('/pengaturan', [PlatformSettingController::class, 'update'])->name('settings.update');

        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
    });

Route::post('/admin/stop-impersonate', [AdminUserController::class, 'stopImpersonating'])
    ->middleware('auth')
    ->name('admin.stop-impersonate');
