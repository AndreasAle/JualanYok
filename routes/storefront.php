<?php

use App\Http\Controllers\PublicSite\CartController;
use App\Http\Controllers\PublicSite\ChatController;
use App\Http\Controllers\PublicSite\StorefrontController;
use App\Http\Controllers\PublicSite\ShippingController;
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
        Route::get('/go/{product:slug}', [StorefrontController::class, 'externalRedirect'])
            ->middleware('throttle:120,1')
            ->name('storefront.external.redirect');
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

        Route::get('/pengiriman/area', [ShippingController::class, 'areas'])
            ->middleware('throttle:30,1')
            ->name('storefront.shipping.areas');
        Route::post('/pengiriman/tarif', [ShippingController::class, 'quotes'])
            ->middleware('throttle:20,1')
            ->name('storefront.shipping.quotes');

        /*
         * Chat with the seller. Guest-friendly: which thread the caller may
         * touch comes from their session or http-only cookie, never from the
         * request, so no conversation id is accepted here at all.
         */
        Route::get('/chat', [ChatController::class, 'show'])
            ->middleware('throttle:60,1')
            ->name('storefront.chat.show');
        Route::post('/chat', [ChatController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('storefront.chat.store');

        /* Basket. Guest-friendly; identified by a per-store cookie. */
        Route::middleware('throttle:60,1')->group(function () {
            Route::post('/keranjang', [CartController::class, 'store'])->name('storefront.cart.store');
            Route::put('/keranjang/{item}', [CartController::class, 'update'])->name('storefront.cart.update');
            Route::delete('/keranjang/{item}', [CartController::class, 'destroy'])->name('storefront.cart.destroy');
            Route::delete('/keranjang', [CartController::class, 'clear'])->name('storefront.cart.clear');
        });
    });
