<?php

declare(strict_types=1);

namespace App\Domain\Business\Models;

use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Enums\BusinessVerificationStatus;
use App\Domain\Business\Enums\CanadianProvince;
use App\Domain\Catalog\Models\Service;
use App\Domain\Payment\Models\Payout;
use App\Domain\User\Models\User;
use App\Support\Concerns\HasUuidv7;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;
    use HasUuidv7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'legal_name',
        'registration_number',
        'verification_status',
        'business_hours',
        'max_bookings_per_day',
        'street',
        'unit',
        'city',
        'province',
        'postal_code',
        'stripe_connect_account_id',
        'stripe_charges_enabled',
        'stripe_payouts_enabled',
    ];

    /**
     * `location` (geography(Point,4326)) is intentionally absent from
     * $fillable/$casts — it has no native Eloquent cast and is written via
     * raw SQL (ST_MakePoint) in the owning Action, never mass-assigned.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_status' => BusinessVerificationStatus::class,
            'business_hours' => 'array',
            'rating_avg' => 'decimal:2',
            'max_bookings_per_day' => 'integer',
            'province' => CanadianProvince::class,
            'stripe_charges_enabled' => 'boolean',
            'stripe_payouts_enabled' => 'boolean',
        ];
    }

    /**
     * CLAUDE.md §7 — sending a quotation is blocked until the connected
     * account can actually receive funds.
     */
    public function canReceiveFunds(): bool
    {
        return $this->stripe_payouts_enabled === true;
    }

    /**
     * Reads `location` back out via raw SQL for the same reason it's
     * written that way — no native Eloquent cast exists for geography(Point).
     *
     * @return array{lat: float, lng: float}|null
     */
    public function locationPoint(): ?array
    {
        $row = DB::selectOne(
            'select ST_Y(location::geometry) as lat, ST_X(location::geometry) as lng from businesses where id = ? and location is not null',
            [$this->id]
        );

        if ($row === null) {
            return null;
        }

        return ['lat' => (float) $row->lat, 'lng' => (float) $row->lng];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<BusinessDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(BusinessDocument::class);
    }

    /**
     * @return HasMany<ProviderAvailability, $this>
     */
    public function availability(): HasMany
    {
        return $this->hasMany(ProviderAvailability::class);
    }

    /**
     * @return HasMany<ProviderAvailabilityOverride, $this>
     */
    public function availabilityOverrides(): HasMany
    {
        return $this->hasMany(ProviderAvailabilityOverride::class, 'business_id');
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'provider_id');
    }

    /**
     * @return HasMany<Payout, $this>
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'provider_id');
    }
}
