<?php

declare(strict_types=1);

namespace App\Domain\Payment\Listeners;

use App\Domain\Payment\Events\PayoutAnomalyDetected;
use App\Domain\Payment\Notifications\PayoutAnomalyDetectedNotification;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Mirrors App\Domain\Business\Listeners\NotifyAdminsOfVerificationSubmission
 * — the existing "notify every admin/super_admin" pattern in this codebase.
 */
class NotifyAdminsOfPayoutAnomaly implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(PayoutAnomalyDetected $event): void
    {
        $admins = User::role([RoleName::Admin->value, RoleName::SuperAdmin->value])->get();

        Notification::send($admins, new PayoutAnomalyDetectedNotification($event));
    }
}
