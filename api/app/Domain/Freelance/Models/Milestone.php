<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Models;

use App\Domain\Freelance\Enums\MilestoneStatus;
use App\Domain\Payment\Models\Payment;
use App\Support\Casts\MoneyCast;
use App\Support\Concerns\HasUuidv7;
use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Milestone extends Model
{
    /** @use HasFactory<MilestoneFactory> */
    use HasFactory;
    use HasUuidv7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contract_id',
        'title',
        'amount',
        'currency',
        'due_date',
        'status',
        'rejection_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'due_date' => 'date',
            'status' => MilestoneStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return HasMany<Deliverable, $this>
     */
    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class);
    }

    /**
     * @return MorphOne<Payment, $this>
     */
    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable');
    }
}
