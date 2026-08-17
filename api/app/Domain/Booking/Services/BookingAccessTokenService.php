<?php

declare(strict_types=1);

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAccessToken;
use App\Domain\Platform\Services\SettingsRepository;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * SRS §6.1 "Booking access tokens" — the only place a plaintext booking
 * access token exists. It is generated here, returned once to the caller,
 * and never persisted or logged; the database holds only its sha256.
 *
 * Every failure mode (unknown, expired, revoked, wrong booking) is
 * indistinguishable to the caller: `resolve()` returns null and the HTTP
 * layer turns that into a 404, never a 403, so the API leaks no signal
 * about whether a booking exists.
 */
final class BookingAccessTokenService
{
    /**
     * 32 random bytes, hex-encoded. Well beyond guessing range, and URL-safe
     * so it survives being placed in the emailed `?token=` link unescaped.
     */
    private const TOKEN_BYTES = 32;

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    /**
     * Issues a fresh token for a booking.
     *
     * @return array{token: BookingAccessToken, plaintext: string} the plaintext
     *                                                             is available here and nowhere else — the caller must hand it
     *                                                             straight to the response/mail and drop it.
     */
    public function issue(Booking $booking, ?string $ip = null): array
    {
        $plaintext = bin2hex(random_bytes(self::TOKEN_BYTES));

        $token = BookingAccessToken::create([
            'booking_id' => $booking->id,
            'token_hash' => $this->hash($plaintext),
            'expires_at' => Date::now()->addDays($this->ttlDays()),
            'created_ip' => $ip,
        ]);

        return ['token' => $token, 'plaintext' => $plaintext];
    }

    /**
     * Resolves a plaintext token to its booking, or null for *any* failure.
     *
     * The lookup is a single indexed equality match on the hash — the
     * plaintext is hashed first, so the comparison the database performs is
     * over a value an attacker cannot steer toward a timing signal. The
     * explicit hash_equals below guards the remaining in-PHP comparison.
     */
    public function resolve(string $plaintext, ?string $expectedBookingNumber = null): ?Booking
    {
        if ($plaintext === '') {
            return null;
        }

        $candidate = BookingAccessToken::query()
            ->with('booking')
            ->where('token_hash', $this->hash($plaintext))
            ->first();

        if ($candidate === null || ! hash_equals($candidate->token_hash, $this->hash($plaintext))) {
            return null;
        }

        if (! $candidate->isUsable()) {
            return null;
        }

        $booking = $candidate->booking;

        if ($booking === null) {
            return null;
        }

        // A token opens exactly one booking. Presenting booking A's token
        // on booking B's URL is as much a miss as an unknown token.
        if ($expectedBookingNumber !== null && ! hash_equals($booking->booking_number, $expectedBookingNumber)) {
            return null;
        }

        $candidate->forceFill(['last_used_at' => Date::now()])->save();

        return $booking;
    }

    /**
     * SRS §6.1 "Claiming": once an account owns the booking, the anonymous
     * credential must stop working.
     */
    public function revokeAllFor(Booking $booking): int
    {
        return BookingAccessToken::query()
            ->where('booking_id', $booking->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Date::now()]);
    }

    private function ttlDays(): int
    {
        return (int) $this->settings->get('guest.booking_token_ttl_days', 30);
    }

    private function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    /**
     * The tracking URL emailed to a guest. The token rides in the query
     * string exactly once; the SPA exchanges it into the API client and
     * strips it with history.replaceState (SRS §6.1).
     */
    public function trackingUrl(Booking $booking, string $plaintext): string
    {
        return rtrim((string) config('app.frontend_url'), '/')
            .'/track?booking='.urlencode($booking->booking_number)
            .'&token='.urlencode($plaintext);
    }

    /**
     * Guards against a plaintext token being echoed anywhere it shouldn't
     * be — used by the log-redaction test and by Str::mask callers.
     */
    public function redact(string $plaintext): string
    {
        return Str::mask($plaintext, '*', 4);
    }
}
