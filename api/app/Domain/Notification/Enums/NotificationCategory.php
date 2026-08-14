<?php

declare(strict_types=1);

namespace App\Domain\Notification\Enums;

/**
 * Groups the SRS §11 notification events into the categories users toggle
 * in `notification_preferences` (one row per user per category). In-app is
 * always on for every category and is not represented here — it isn't a
 * preference, it's the floor.
 */
enum NotificationCategory: string
{
    case Booking = 'booking';
    case Quotation = 'quotation';
    case Payment = 'payment';
    case Freelance = 'freelance';
    case Review = 'review';
    case Verification = 'verification';
}
