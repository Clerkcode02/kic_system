<?php

declare(strict_types=1);

namespace App\Domain\Quotation\Models;

use App\Domain\Booking\Models\Booking;
use App\Domain\Quotation\Enums\QuotationStatus;
use App\Support\Casts\MoneyCast;
use App\Support\Concerns\HasUuidv7;
use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory;
    use HasUuidv7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'previous_quotation_id',
        'labor_cost',
        'materials_cost',
        'additional_fees',
        'platform_fee',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'deposit_percentage',
        'currency',
        'valid_until',
        'revision_number',
        'status',
        'reminder_24h_sent_at',
        'reminder_2h_sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'labor_cost' => MoneyCast::class,
            'materials_cost' => MoneyCast::class,
            'additional_fees' => MoneyCast::class,
            'platform_fee' => MoneyCast::class,
            'tax_amount' => MoneyCast::class,
            'discount_amount' => MoneyCast::class,
            'total_amount' => MoneyCast::class,
            'deposit_percentage' => 'decimal:2',
            'valid_until' => 'datetime',
            'revision_number' => 'integer',
            'status' => QuotationStatus::class,
            'reminder_24h_sent_at' => 'datetime',
            'reminder_2h_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return HasMany<QuotationLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(QuotationLineItem::class);
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function previousQuotation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_quotation_id');
    }
}
