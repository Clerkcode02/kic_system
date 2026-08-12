<?php

declare(strict_types=1);

namespace App\Domain\Business\Models;

use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Enums\BusinessVerificationStatus;
use App\Domain\Catalog\Models\Service;
use App\Domain\Payment\Models\Payout;
use App\Domain\User\Models\User;
use App\Support\Concerns\HasUuidv7;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        ];
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
