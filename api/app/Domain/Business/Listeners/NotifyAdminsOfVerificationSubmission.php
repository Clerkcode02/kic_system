<?php

declare(strict_types=1);

namespace App\Domain\Business\Listeners;

use App\Domain\Business\Events\BusinessSubmittedForVerification;
use App\Domain\Business\Notifications\BusinessSubmittedForVerificationNotification;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyAdminsOfVerificationSubmission implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(BusinessSubmittedForVerification $event): void
    {
        $admins = User::role([RoleName::Admin->value, RoleName::SuperAdmin->value])->get();

        Notification::send($admins, new BusinessSubmittedForVerificationNotification($event->business));
    }
}
