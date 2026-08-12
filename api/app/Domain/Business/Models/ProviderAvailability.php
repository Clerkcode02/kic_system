<?php

declare(strict_types=1);

namespace App\Domain\Business\Models;

use App\Support\Concerns\HasUuidv7;
use Database\Factories\ProviderAvailabilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderAvailability extends Model
{
    /** @use HasFactory<ProviderAvailabilityFactory> */
    use HasFactory;
    use HasUuidv7;

    /**
     * Eloquent's default pluralizer treats "Availability" as ending in "y"
     * and produces "provider_availabilities"; the actual table (per the
     * migration) is singular.
     *
     * @var string
     */
    protected $table = 'provider_availability';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
