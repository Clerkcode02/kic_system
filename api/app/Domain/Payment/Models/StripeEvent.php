<?php

declare(strict_types=1);

namespace App\Domain\Payment\Models;

use App\Support\Concerns\HasUuidv7;
use Database\Factories\StripeEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StripeEvent extends Model
{
    /** @use HasFactory<StripeEventFactory> */
    use HasFactory;
    use HasUuidv7;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'stripe_event_id',
        'type',
        'payload',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
