<?php

declare(strict_types=1);

namespace App\Domain\Payment\Models;

use App\Domain\Business\Models\Business;
use App\Domain\Payment\Enums\PayoutStatus;
use App\Support\Casts\MoneyCast;
use App\Support\Concerns\HasUuidv7;
use Database\Factories\PayoutFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    /** @use HasFactory<PayoutFactory> */
    use HasFactory;
    use HasUuidv7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider_id',
        'amount',
        'currency',
        'stripe_transfer_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'status' => PayoutStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'provider_id');
    }
}
