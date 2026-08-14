<?php

declare(strict_types=1);

namespace App\Domain\Payment\Models;

use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Support\Casts\MoneyCast;
use App\Support\Concerns\HasUuidv7;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;
    use HasUuidv7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payable_type',
        'payable_id',
        'payout_id',
        'stripe_payment_intent_id',
        'stripe_transfer_id',
        'amount',
        'platform_fee_amount',
        'provider_net_amount',
        'currency',
        'type',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'platform_fee_amount' => MoneyCast::class,
            'provider_net_amount' => MoneyCast::class,
            'type' => PaymentType::class,
            'status' => PaymentStatus::class,
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * @return BelongsTo<Payout, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
