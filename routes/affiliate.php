<?php

use App\Http\Controllers\Affiliate\AffiliateDashboardController;
use App\Http\Controllers\Affiliate\AffiliateLinkController;
use App\Http\Controllers\Affiliate\MarketplaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->prefix('affiliate')
    ->name('affiliate.')
    ->group(function () {
        Route::get('/', [AffiliateDashboardController::class, 'index'])->name('dashboard');

        Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace');
        Route::post('/marketplace/{product}/gabung', [MarketplaceController::class, 'join'])
            ->middleware('throttle:30,1')
            ->name('marketplace.join');

        Route::get('/link', [AffiliateLinkController::class, 'index'])->name('links.index');
        Route::post('/link', [AffiliateLinkController::class, 'store'])->name('links.store');
        Route::delete('/link/{link}', [AffiliateLinkController::class, 'destroy'])->name('links.destroy');

        Route::get('/komisi', [AffiliateDashboardController::class, 'commissions'])->name('commissions');
    });
