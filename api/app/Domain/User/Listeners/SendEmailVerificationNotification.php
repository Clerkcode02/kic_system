<?php

declare(strict_types=1);

namespace App\Domain\User\Listeners;

use App\Domain\User\Events\UserRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendEmailVerificationNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(UserRegistered $event): void
    {
        if (! $event->user->hasVerifiedEmail()) {
            $event->user->sendEmailVerificationNotification();
        }
    }
}
