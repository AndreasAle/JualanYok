<?php

namespace App\Providers;

use App\Events\OrderPaid;
use App\Listeners\HandleOrderPaid;
use App\Listeners\SendWelcomeEmail;
use App\Payments\PaymentManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
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
        Event::listen(Registered::class, SendWelcomeEmail::class);

        $this->localiseAuthEmails();

        // Catches lazy-loading regressions in development before they become
        // N+1 queries in production.
        Model::preventLazyLoading($this->app->isLocal());
        Model::preventSilentlyDiscardingAttributes($this->app->isLocal());

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        date_default_timezone_set(config('app.timezone'));
    }

    /**
     * Laravel's built-in verification and password-reset mails are English and
     * unbranded. The rest of the product speaks Indonesian, so these do too — an
     * account email that reads like a different service invites people to treat
     * it as phishing.
     */
    private function localiseAuthEmails(): void
    {
        VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            return (new MailMessage)
                ->subject('Konfirmasi alamat email kamu — JualanYok')
                ->greeting('Halo!')
                ->line('Klik tombol di bawah buat mengonfirmasi alamat email kamu.')
                ->action('Konfirmasi Email', $url)
                ->line('Kalau kamu nggak pernah membuat akun di JualanYok, abaikan saja email ini.');
        });

        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], absolute: false));

            $minutes = config('auth.passwords.users.expire', 60);

            return (new MailMessage)
                ->subject('Atur ulang password JualanYok')
                ->greeting('Halo!')
                ->line('Kamu (atau seseorang) meminta pengaturan ulang password untuk akun ini.')
                ->action('Atur Ulang Password', $url)
                ->line("Tautan ini berlaku {$minutes} menit.")
                ->line('Kalau bukan kamu, abaikan email ini — passwordmu tetap aman.');
        });
    }
}
