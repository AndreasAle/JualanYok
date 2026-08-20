<?php

use App\Http\Controllers\PublicSite\StorefrontController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public storefronts
|--------------------------------------------------------------------------
| Registered last so every platform route wins over a creator username. The
| username pattern additionally excludes anything in the reserved list at
| validation time (see App\Support\Username).
*/

Route::prefix('{store:username}')
    ->where(['store' => '[a-z0-9][a-z0-9._-]{1,29}'])
    ->group(function () {
        Route::get('/', [StorefrontController::class, 'show'])->name('storefront.show');
        Route::get('/p/{product:slug}', [StorefrontController::class, 'product'])->name('storefront.product');
        Route::get('/preview', [StorefrontController::class, 'preview'])
            ->middleware('auth')
            ->name('storefront.preview');

        Route::post('/blocks/{block}/click', [StorefrontController::class, 'trackClick'])
            ->middleware('throttle:120,1')
            ->name('storefront.block.click');

        Route::post('/leads', [StorefrontController::class, 'submitLead'])
            ->middleware('throttle:10,1')
            ->name('storefront.leads');

        Route::post('/checkout', [StorefrontController::class, 'checkout'])
            ->middleware('throttle:20,1')
            ->name('storefront.checkout');
    });
