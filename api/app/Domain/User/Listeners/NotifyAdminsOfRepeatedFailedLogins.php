<?php

declare(strict_types=1);

namespace App\Domain\User\Listeners;

use App\Domain\User\Enums\RoleName;
use App\Domain\User\Events\RepeatedFailedLoginsDetected;
use App\Domain\User\Models\User;
use App\Domain\User\Notifications\RepeatedFailedLoginsNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Mirrors App\Domain\Business\Listeners\NotifyAdminsOfVerificationSubmission
 * — the existing "notify every admin/super_admin" pattern in this codebase.
 */
class NotifyAdminsOfRepeatedFailedLogins implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(RepeatedFailedLoginsDetected $event): void
    {
        $admins = User::role([RoleName::Admin->value, RoleName::SuperAdmin->value])->get();

        Notification::send($admins, new RepeatedFailedLoginsNotification($event));
    }
}
