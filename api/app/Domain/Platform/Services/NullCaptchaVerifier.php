<?php

declare(strict_types=1);

namespace App\Domain\Platform\Services;

/**
 * The default {@see CaptchaVerifier}: no provider configured, so the
 * challenge is simply not enforced. Kept behind the same settings flag as a
 * real implementation would be, so switching one in is a binding change
 * rather than a control-flow change.
 *
 * If an operator turns the flag on without binding a real verifier, this
 * fails *closed* — an enabled-but-unimplemented captcha rejecting bookings
 * is a visible misconfiguration; silently accepting everything is not.
 */
final class NullCaptchaVerifier implements CaptchaVerifier
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        return ! $this->isEnabled();
    }

    public function isEnabled(): bool
    {
        return (bool) $this->settings->get('guest.booking_captcha_enabled', false);
    }
}
