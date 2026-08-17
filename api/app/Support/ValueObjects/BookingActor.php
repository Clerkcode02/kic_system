<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Domain\Booking\Models\Booking;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use InvalidArgumentException;

/**
 * Who is acting on a booking (SRS §6.1). A booking has exactly one actor —
 * a registered {@see User} or a guest contact triple — and every booking
 * Action takes this value object rather than a `User`, so the guest and
 * registered paths run identical business logic with no fork.
 *
 * Nothing outside this class may branch on "customer_id is null" to decide
 * actor kind; ask {@see isGuest()} instead (CLAUDE.md §5 "Guest booking").
 */
final class BookingActor
{
    private function __construct(
        public readonly ?User $user,
        public readonly ?string $guestName,
        public readonly ?string $guestEmail,
        public readonly ?string $guestPhone,
    ) {
    }

    public static function user(User $user): self
    {
        return new self($user, null, null, null);
    }

    public static function guest(string $name, string $email, string $phone): self
    {
        foreach (['name' => $name, 'email' => $email, 'phone' => $phone] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("A guest actor requires a non-empty {$field}.");
            }
        }

        return new self(null, trim($name), trim($email), trim($phone));
    }

    /**
     * Reconstructs the actor a booking was placed by — used where an Action
     * needs the owning actor rather than the calling one (e.g. notifying
     * the customer of a provider-initiated change).
     */
    public static function fromBooking(Booking $booking): self
    {
        if ($booking->customer !== null) {
            return self::user($booking->customer);
        }

        return self::guest(
            (string) $booking->guest_name,
            (string) $booking->guest_email,
            (string) $booking->guest_phone,
        );
    }

    /**
     * Lowercased and trimmed. The single definition of "the same email" for
     * claiming, the per-email booking cap, idempotency scoping and rate
     * limiting — all of which must agree or the guarantees they provide
     * disagree at the edges.
     */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function isGuest(): bool
    {
        return $this->user === null;
    }

    public function userId(): ?string
    {
        return $this->user?->id;
    }

    // Branch on isGuest() rather than coalescing off the user, so these
    // read the same way as the rest of the codebase and stay correct if a
    // User ever gains a nullable name/email.

    public function name(): string
    {
        return $this->isGuest() ? (string) $this->guestName : (string) $this->user?->name;
    }

    public function email(): string
    {
        return $this->isGuest() ? (string) $this->guestEmail : (string) $this->user?->email;
    }

    public function phone(): ?string
    {
        return $this->isGuest() ? $this->guestPhone : $this->user?->phone;
    }

    public function normalizedEmail(): string
    {
        return self::normalizeEmail($this->email());
    }

    public function isAdmin(): bool
    {
        return $this->user?->hasAnyRole([RoleName::Admin->value, RoleName::SuperAdmin->value]) ?? false;
    }

    /**
     * Null for a guest — `audit_logs.actor_id` is a users FK with nowhere
     * to put one. {@see auditActorLabel()} carries the identity instead.
     */
    public function auditActorId(): ?string
    {
        return $this->userId();
    }

    /**
     * `guest:<sha256 of the normalized email>` — never a raw email
     * (SRS §13, CLAUDE.md §2).
     */
    public function auditActorLabel(): ?string
    {
        return $this->isGuest()
            ? 'guest:'.hash('sha256', $this->normalizedEmail())
            : null;
    }

    /**
     * The identity an `Idempotency-Key` is unique within, so one caller's
     * key can never collide with another's.
     */
    public function idempotencyScope(): string
    {
        return $this->isGuest()
            ? 'guest:'.hash('sha256', $this->normalizedEmail())
            : 'user:'.$this->userId();
    }

    /**
     * The guest columns to persist on a booking. Empty for a registered
     * actor, which keeps the "exactly one actor" CHECK satisfied by
     * construction rather than by the caller remembering to null them out.
     *
     * @return array<string, string|null>
     */
    public function bookingAttributes(): array
    {
        if (! $this->isGuest()) {
            return [
                'customer_id' => $this->userId(),
                'guest_name' => null,
                'guest_email' => null,
                'guest_phone' => null,
                'guest_email_normalized' => null,
            ];
        }

        return [
            'customer_id' => null,
            'guest_name' => $this->guestName,
            'guest_email' => $this->guestEmail,
            'guest_phone' => $this->guestPhone,
            'guest_email_normalized' => $this->normalizedEmail(),
        ];
    }

    /**
     * True when this actor is the one who placed the booking. The ownership
     * check every guest-facing Action needs, with no `customer_id === null`
     * branching at the call site.
     */
    public function owns(Booking $booking): bool
    {
        if ($this->isGuest()) {
            return $booking->isGuest()
                && hash_equals((string) $booking->guest_email_normalized, $this->normalizedEmail());
        }

        return $booking->customer_id !== null && $booking->customer_id === $this->userId();
    }
}
