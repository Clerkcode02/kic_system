<?php

declare(strict_types=1);

namespace App\Domain\Review\Notifications;

use App\Domain\Notification\Concerns\ResolvesNotificationChannels;
use App\Domain\Notification\Contracts\ChannelResolvable;
use App\Domain\Notification\Enums\NotificationCategory;
use App\Domain\Review\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewReceivedNotification extends Notification implements ChannelResolvable, ShouldQueue
{
    use Queueable;
    use ResolvesNotificationChannels;

    public function __construct(public readonly Review $review)
    {
        $this->onQueue('notifications');
    }

    protected function notificationCategory(): NotificationCategory
    {
        return NotificationCategory::Review;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'review_id' => $this->review->id,
            'rating' => $this->review->rating,
            'message' => "You received a {$this->review->rating}-star review.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('You received a new review')
            ->line("You received a {$this->review->rating}-star review.");
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'New review',
            'body' => "{$this->review->rating} stars",
        ];
    }
}
