<?php

declare(strict_types=1);

namespace App\Domain\Platform\Services;

/**
 * SRS §6.1 "Abuse controls". The public booking endpoint is the platform's
 * only unauthenticated write surface, so it gets a captcha seam from day
 * one — bound to {@see NullCaptchaVerifier} and switched on by the
 * `guest.booking_captcha_enabled` platform setting.
 *
 * Wiring a real provider later is one class plus one binding; no calling
 * code changes.
 */
interface CaptchaVerifier
{
    /**
     * Whether the challenge response the client submitted is valid.
     * Implementations must fail closed on transport errors.
     */
    public function verify(?string $token, ?string $ip = null): bool;

    /**
     * Whether a challenge is being enforced at all right now. When false,
     * callers must not require a token.
     */
    public function isEnabled(): bool;
}
