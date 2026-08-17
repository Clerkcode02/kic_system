<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use App\Domain\User\Models\FailedLoginAttempt;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SRS §17 anomaly signal 1: repeated failed logins for the same email.
 * Dispatched by App\Domain\User\Services\FailedLoginMonitor once the
 * failure count for an email crosses the configured threshold within the
 * configured window — see that class for the debounce rule.
 *
 * `$latestAttempt` anchors the audit-log entry to a real row (the one
 * that tripped the threshold) since a security event about an email that
 * may not even belong to a registered user has no User/actor to hang off.
 */
class RepeatedFailedLoginsDetected implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FailedLoginAttempt $latestAttempt,
        public readonly string $email,
        public readonly int $attemptCount,
        public readonly int $windowMinutes,
    ) {
    }

    public function auditActorId(): ?string
    {
        return null;
    }

    public function auditAction(): string
    {
        return 'user.repeated_failed_logins_detected';
    }

    public function auditableType(): string
    {
        return 'failed_login_attempt';
    }

    public function auditableId(): string
    {
        return $this->latestAttempt->id;
    }

    public function auditBeforeState(): ?array
    {
        return null;
    }

    public function auditAfterState(): ?array
    {
        return [
            'email' => $this->email,
            'attempt_count' => $this->attemptCount,
            'window_minutes' => $this->windowMinutes,
        ];
    }
}
