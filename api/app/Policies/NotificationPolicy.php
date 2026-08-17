<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\User\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class NotificationPolicy
{
    public function read(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_id === $user->id;
    }
}
