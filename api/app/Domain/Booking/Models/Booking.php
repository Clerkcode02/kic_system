<?php

declare(strict_types=1);

namespace App\Domain\Booking\Models;

use App\Domain\Booking\Enums\BookingPaymentStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Models\Service;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Payment\Models\Payment;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\Review\Models\Review;
use App\Domain\User\Models\Address;
use App\Domain\User\Models\User;
use App\Support\Concerns\HasUuidv7;
use App\Support\ValueObjects\BookingActor;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;
    use HasUuidv7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'booking_number',
        'customer_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'guest_email_normalized',
        'claimed_by_user_id',
        'claimed_at',
        'stripe_customer_id',
        'provider_id',
        'service_id',
        'scheduled_date',
        'time_slot_start',
        'time_slot_end',
        'address_id',
        'service_address_line1',
        'service_address_line2',
        'service_address_city',
        'service_address_province',
        'service_address_postal_code',
        'lat',
        'lng',
        'notes',
        'status',
        'payment_status',
        'provider_completed_at',
        'quotation_nudge_sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'status' => BookingStatus::class,
            'payment_status' => BookingPaymentStatus::class,
            'provider_completed_at' => 'datetime',
            'quotation_nudge_sent_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    /**
     * @var list<string>
     */
    protected $hidden = [
        // Never serialized by any Resource, but hidden as a backstop so a
        // stray toArray()/log of a Booking can't leak guest PII
        // (CLAUDE.md §2 — guest emails appear in the trail only as a hash).
        'guest_email',
        'guest_email_normalized',
        'guest_phone',
    ];

    /**
     * SRS §6.1: a booking has exactly one actor. This is the only sanctioned
     * way to ask which kind — nothing may branch on `customer_id === null`
     * directly (CLAUDE.md §5 "Guest booking").
     */
    public function isGuest(): bool
    {
        return $this->customer_id === null;
    }

    /**
     * Where booking correspondence goes, regardless of actor kind.
     */
    public function contactEmail(): string
    {
        return $this->isGuest()
            ? (string) $this->guest_email
            : (string) $this->customer?->email;
    }

    public function contactName(): string
    {
        return $this->isGuest()
            ? (string) $this->guest_name
            : (string) $this->customer?->name;
    }

    public function contactPhone(): ?string
    {
        return $this->isGuest() ? $this->guest_phone : $this->customer?->phone;
    }

    /**
     * The actor that placed this booking.
     */
    public function actor(): BookingActor
    {
        return BookingActor::fromBooking($this);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * The account that later claimed this guest booking on email
     * verification (SRS §6.1 "Claiming"). Retained alongside `customer_id`
     * so the trail shows the booking was *placed* as a guest.
     *
     * @return BelongsTo<User, $this>
     */
    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    /**
     * @return HasMany<BookingAccessToken, $this>
     */
    public function accessTokens(): HasMany
    {
        return $this->hasMany(BookingAccessToken::class);
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'provider_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Address, $this>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * @return HasMany<BookingStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class);
    }

    /**
     * @return HasMany<BookingAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(BookingAttachment::class);
    }

    /**
     * @return HasMany<Quotation, $this>
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * @return MorphOne<Payment, $this>
     */
    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable');
    }

    /**
     * @return MorphMany<Review, $this>
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * @return MorphMany<Dispute, $this>
     */
    public function disputes(): MorphMany
    {
        return $this->morphMany(Dispute::class, 'disputable');
    }
}
