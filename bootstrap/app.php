<?php

use App\Http\Middleware\EnsureIsAdmin;
use App\Http\Middleware\EnsureIsCreator;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            foreach (['auth', 'creator', 'admin', 'member', 'affiliate', 'storefront'] as $group) {
                Route::middleware('web')
                    ->group(base_path("routes/{$group}.php"));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'creator' => EnsureIsCreator::class,
            'admin' => EnsureIsAdmin::class,
        ]);

        // The payment gateway signs its callbacks; it cannot send a CSRF token.
        $middleware->validateCsrfTokens(except: [
            'webhooks/payments/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render friendly Inertia error pages instead of Laravel's default
        // HTML for anything the user could realistically hit.
        $exceptions->respond(function ($response, Throwable $e, Request $request) {
            if (! app()->environment(['local', 'testing'])
                && $e instanceof HttpExceptionInterface
                && in_array($response->getStatusCode(), [403, 404, 419, 429, 500, 503], true)
                && ! $request->expectsJson()
            ) {
                return inertia('Error', [
                    'status' => $response->getStatusCode(),
                    'message' => $e->getMessage(),
                ])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            if ($response->getStatusCode() === 419 && ! $request->expectsJson()) {
                return back()->with('error', 'Halaman kedaluwarsa, coba lagi ya.');
            }

            return $response;
        });
    })->create();
