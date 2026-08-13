<?php

declare(strict_types=1);

namespace App\Domain\Business\Models;

use App\Support\Concerns\HasUuidv7;
use Database\Factories\ProviderAvailabilityOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderAvailabilityOverride extends Model
{
    /** @use HasFactory<ProviderAvailabilityOverrideFactory> */
    use HasFactory;
    use HasUuidv7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'date',
        'is_blackout',
        'start_time',
        'end_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_blackout' => 'boolean',
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
