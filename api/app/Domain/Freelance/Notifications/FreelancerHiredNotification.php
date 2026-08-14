<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Notifications;

use App\Domain\Freelance\Models\Contract;
use App\Domain\Notification\Concerns\ResolvesNotificationChannels;
use App\Domain\Notification\Contracts\ChannelResolvable;
use App\Domain\Notification\Enums\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FreelancerHiredNotification extends Notification implements ChannelResolvable, ShouldQueue
{
    use Queueable;
    use ResolvesNotificationChannels;

    public function __construct(public readonly Contract $contract)
    {
        $this->onQueue('notifications');
    }

    protected function notificationCategory(): NotificationCategory
    {
        return NotificationCategory::Freelance;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'contract_id' => $this->contract->id,
            'message' => 'You were hired! A new contract has been created.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("You're hired!")
            ->line('The client hired you and a new contract has been created.')
            ->action('View contract', $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => "You're hired!",
            'body' => 'A new contract has been created.',
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/contracts/{$this->contract->id}";
    }
}
