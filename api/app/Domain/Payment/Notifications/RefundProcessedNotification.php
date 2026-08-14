<?php

declare(strict_types=1);

namespace App\Domain\Payment\Notifications;

use App\Domain\Notification\Concerns\ResolvesNotificationChannels;
use App\Domain\Notification\Contracts\ChannelResolvable;
use App\Domain\Notification\Enums\NotificationCategory;
use App\Domain\Payment\Models\Refund;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundProcessedNotification extends Notification implements ChannelResolvable, ShouldQueue
{
    use Queueable;
    use ResolvesNotificationChannels;

    public function __construct(public readonly Refund $refund)
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
            'refund_id' => $this->refund->id,
            'payment_id' => $this->refund->payment_id,
            'amount' => $this->refund->amount->toDecimal(),
            'currency' => $this->refund->currency,
            'message' => 'A refund of '.$this->refund->amount->toDecimal().' '.$this->refund->currency.' was processed.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Refund processed')
            ->line('A refund of '.$this->refund->amount->toDecimal().' '.$this->refund->currency.' was processed to your original payment method.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Refund processed',
            'body' => $this->refund->amount->toDecimal().' '.$this->refund->currency,
        ];
    }
}
