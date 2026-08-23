<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * One welcome mail per new account, whichever way they signed up.
 */
class SendWelcomeEmail implements ShouldQueue
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        // A Google account arrives already verified, so the copy differs.
        $user->notify(new WelcomeNotification(viaGoogle: $user->google_id !== null));
    }
}
