<?php

declare(strict_types=1);

namespace App\Domain\Payment\Notifications;

use App\Domain\Notification\Concerns\ResolvesNotificationChannels;
use App\Domain\Notification\Contracts\ChannelResolvable;
use App\Domain\Notification\Enums\NotificationCategory;
use App\Domain\Payment\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutCompletedNotification extends Notification implements ChannelResolvable, ShouldQueue
{
    use Queueable;
    use ResolvesNotificationChannels;

    public function __construct(public readonly Payout $payout)
    {
        $this->onQueue('notifications');
    }

    protected function notificationCategory(): NotificationCategory
    {
        return NotificationCategory::Payment;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'payout_id' => $this->payout->id,
            'amount' => $this->payout->amount->toDecimal(),
            'currency' => $this->payout->currency,
            'message' => 'A payout of '.$this->payout->amount->toDecimal().' '.$this->payout->currency.' was completed.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Payout completed')
            ->line('A payout of '.$this->payout->amount->toDecimal().' '.$this->payout->currency.' has been sent to your connected account.')
            ->action('View earnings', rtrim((string) config('app.frontend_url'), '/').'/provider/earnings');
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Payout completed',
            'body' => $this->payout->amount->toDecimal().' '.$this->payout->currency,
            'url' => rtrim((string) config('app.frontend_url'), '/').'/provider/earnings',
        ];
    }
}
