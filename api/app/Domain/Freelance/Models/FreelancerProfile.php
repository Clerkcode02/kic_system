<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Models;

use App\Domain\Freelance\Enums\FreelancerApprovalStatus;
use App\Domain\User\Models\User;
use App\Support\Casts\MoneyCast;
use App\Support\Concerns\HasUuidv7;
use Database\Factories\FreelancerProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FreelancerProfile extends Model
{
    /** @use HasFactory<FreelancerProfileFactory> */
    use HasFactory;
    use HasUuidv7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'headline',
        'bio',
        'hourly_rate',
        'currency',
        'years_experience',
        'approval_status',
        'rating_avg',
        'stripe_connect_account_id',
        'stripe_charges_enabled',
        'stripe_payouts_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hourly_rate' => MoneyCast::class,
            'years_experience' => 'integer',
            'approval_status' => FreelancerApprovalStatus::class,
            'rating_avg' => 'decimal:2',
            'stripe_charges_enabled' => 'boolean',
            'stripe_payouts_enabled' => 'boolean',
        ];
    }

    /**
     * CLAUDE.md §7 — hiring a freelancer is blocked until the connected
     * account can actually receive escrow transfers.
     */
    public function canReceiveFunds(): bool
    {
        return $this->stripe_payouts_enabled === true;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<FreelancerSkill, $this>
     */
    public function skills(): HasMany
    {
        return $this->hasMany(FreelancerSkill::class);
    }

    /**
     * @return HasMany<PortfolioItem, $this>
     */
    public function portfolioItems(): HasMany
    {
        return $this->hasMany(PortfolioItem::class);
    }

    /**
     * @return HasMany<Proposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class, 'freelancer_id');
    }
}
