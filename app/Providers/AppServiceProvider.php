<?php

namespace App\Providers;

use App\Events\OrderPaid;
use App\Listeners\HandleOrderPaid;
use App\Payments\PaymentManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentManager::class);
    }

    public function boot(): void
    {
        Event::listen(OrderPaid::class, HandleOrderPaid::class);

        // Catches lazy-loading regressions in development before they become
        // N+1 queries in production.
        Model::preventLazyLoading($this->app->isLocal());
        Model::preventSilentlyDiscardingAttributes($this->app->isLocal());

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        date_default_timezone_set(config('app.timezone'));
    }
}
