<?php

use App\Http\Controllers\PublicSite\CheckoutController;
use App\Http\Controllers\PublicSite\DownloadController;
use App\Http\Controllers\PublicSite\LandingController;
use App\Http\Controllers\PublicSite\MarketplaceController;
use App\Http\Controllers\PublicSite\MarketplaceSeoController;
use App\Http\Controllers\PublicSite\OrderAccessController;
use App\Http\Controllers\PublicSite\PaymentWebhookController;
use App\Http\Controllers\PublicSite\ShippingWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketing site
|--------------------------------------------------------------------------
*/

Route::get('/', [LandingController::class, 'home'])->name('home');
Route::get('/pricing', [LandingController::class, 'pricing'])->name('pricing');
Route::get('/features', [LandingController::class, 'features'])->name('features');
Route::get('/templates', [LandingController::class, 'templates'])->name('templates');
Route::get('/templates/{template:slug}/demo', [LandingController::class, 'templateDemo'])
    ->name('templates.demo');
Route::get('/contact', [LandingController::class, 'contact'])->name('contact');
Route::post('/contact', [LandingController::class, 'submitContact'])
    ->middleware('throttle:5,1')
    ->name('contact.submit');
Route::get('/faq', [LandingController::class, 'faq'])->name('faq');
Route::get('/sitemap.xml', [MarketplaceSeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [MarketplaceSeoController::class, 'robots'])->name('robots');

/* Creator marketplace. Commerce actions continue through the established
 * storefront so cart, checkout, payment, fulfilment, and ledger logic stay
 * in one source of truth. */
Route::get('/explore', [MarketplaceController::class, 'index'])->name('marketplace.index');
Route::get('/categories/{category:slug}', [MarketplaceController::class, 'category'])
    ->name('marketplace.categories.show');
Route::get('/products/{store:username}/{product:slug}', [MarketplaceController::class, 'product'])
    ->name('marketplace.products.show');
Route::get('/@{store:username}', [MarketplaceController::class, 'creator'])
    ->name('marketplace.creators.show');

foreach (['terms', 'privacy', 'refund-policy'] as $slug) {
    Route::get("/{$slug}", fn () => app(LandingController::class)->page($slug))
        ->name('pages.'.str_replace('-', '.', $slug));
}

/*
|--------------------------------------------------------------------------
| Checkout & payments
|--------------------------------------------------------------------------
| Checkout lives at the top level (not under /{username}) so the URL stays
| stable across stores and cannot be shadowed by a creator username.
*/

/*
 * Permanent delivery page for one order, reachable without an account.
 * The token is the credential; see OrderAccessController.
 */
Route::prefix('pesanan/{token}')->name('order.')->group(function () {
    Route::get('/', [OrderAccessController::class, 'show'])->name('access');
    Route::get('/unduh/{access}', [OrderAccessController::class, 'download'])
        ->middleware('throttle:60,1')
        ->name('access.download');
    Route::post('/simpan', [OrderAccessController::class, 'claim'])
        ->middleware(['auth', 'throttle:10,1'])
        ->name('access.claim');
    Route::post('/diterima', [OrderAccessController::class, 'confirmReceipt'])
        ->middleware('throttle:10,1')
        ->name('access.confirm-receipt');
    Route::post('/komplain', [OrderAccessController::class, 'openDispute'])
        ->middleware('throttle:5,1')
        ->name('access.dispute');
});

Route::prefix('checkout')->name('checkout.')->group(function () {
    Route::get('/{order:number}', [CheckoutController::class, 'show'])->name('show');
    Route::post('/{order:number}/pay', [CheckoutController::class, 'pay'])
        ->middleware('throttle:20,1')
        ->name('pay');
    Route::get('/{order:number}/status', [CheckoutController::class, 'status'])->name('status');
    // POST is used by the Inertia button. GET is a safe, idempotent fallback
    // for browsers/CDNs that open the status-check URL as a normal navigation.
    Route::match(['get', 'post'], '/{order:number}/check-status', [CheckoutController::class, 'syncStatus'])
        ->middleware('throttle:12,1')
        ->name('status.sync');
    Route::post('/{order:number}/retry', [CheckoutController::class, 'retry'])
        ->middleware('throttle:10,1')
        ->name('retry');
});

// Development-only simulator that stands in for a real gateway callback.
Route::post('/pay/simulate/{payment}', [CheckoutController::class, 'simulate'])
    ->middleware('throttle:30,1')
    ->name('payment.simulate');

Route::post('/webhooks/payments/{provider}', PaymentWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.payments');
Route::post('/webhooks/shipping/biteship', ShippingWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.shipping.biteship');

/*
|--------------------------------------------------------------------------
| Signed downloads
|--------------------------------------------------------------------------
*/

Route::get('/downloads/{token}', [DownloadController::class, 'serve'])
    ->middleware('signed')
    ->name('downloads.serve');
