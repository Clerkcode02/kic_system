<?php

declare(strict_types=1);

namespace App\Domain\User\Notifications;

use App\Domain\User\Events\RepeatedFailedLoginsDetected;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class RepeatedFailedLoginsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly RepeatedFailedLoginsDetected $event,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'email' => $this->event->email,
            'attempt_count' => $this->event->attemptCount,
            'window_minutes' => $this->event->windowMinutes,
            'message' => "{$this->event->attemptCount} failed login attempts for {$this->event->email} in the last {$this->event->windowMinutes} minutes.",
        ];
    }
}
