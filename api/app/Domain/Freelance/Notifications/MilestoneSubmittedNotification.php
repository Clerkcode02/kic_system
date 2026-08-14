<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Notifications;

use App\Domain\Freelance\Models\Milestone;
use App\Domain\Notification\Concerns\ResolvesNotificationChannels;
use App\Domain\Notification\Contracts\ChannelResolvable;
use App\Domain\Notification\Enums\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MilestoneSubmittedNotification extends Notification implements ChannelResolvable, ShouldQueue
{
    use Queueable;
    use ResolvesNotificationChannels;

    public function __construct(public readonly Milestone $milestone)
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
            'milestone_id' => $this->milestone->id,
            'contract_id' => $this->milestone->contract_id,
            'message' => "Milestone \"{$this->milestone->title}\" was submitted for your review.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Milestone submitted for review')
            ->line("The freelancer submitted \"{$this->milestone->title}\" for your review.")
            ->action('Review milestone', $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Milestone submitted',
            'body' => $this->milestone->title,
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/contracts/{$this->milestone->contract_id}";
    }
}
