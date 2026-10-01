<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Models;

use App\Domain\Catalog\Models\Category;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Platform\Models\Attachment;
use App\Domain\Review\Models\Review;
use App\Domain\User\Models\User;
use App\Support\Casts\MoneyCast;
use App\Support\Concerns\HasUuidv7;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;
    use HasUuidv7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'category_id',
        'title',
        'description',
        'budget_min',
        'budget_max',
        'currency',
        'deadline',
        'required_skills',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'budget_min' => MoneyCast::class,
            'budget_max' => MoneyCast::class,
            'deadline' => 'date',
            'required_skills' => 'array',
            'status' => ProjectStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Proposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    /**
     * @return HasOne<Contract, $this>
     */
    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    /**
     * @return MorphMany<Review, $this>
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * Project briefs and reference files. Shares the generic `attachments`
     * table and the presign/confirm flow (ProjectPolicy::manageEvidence
     * gates who may use it).
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * @return MorphMany<Dispute, $this>
     */
    public function disputes(): MorphMany
    {
        return $this->morphMany(Dispute::class, 'disputable');
    }
}
