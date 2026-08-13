<?php

declare(strict_types=1);

namespace App\Domain\Business\Notifications;

use App\Domain\Business\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BusinessSubmittedForVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Business $business,
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
            'business_id' => $this->business->id,
            'legal_name' => $this->business->legal_name,
            'message' => "{$this->business->legal_name} submitted their business for verification.",
        ];
    }
}
