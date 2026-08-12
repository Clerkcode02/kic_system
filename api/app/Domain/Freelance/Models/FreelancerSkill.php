<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Models;

use App\Domain\Freelance\Enums\SkillProficiencyLevel;
use App\Support\Concerns\HasUuidv7;
use Database\Factories\FreelancerSkillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreelancerSkill extends Model
{
    /** @use HasFactory<FreelancerSkillFactory> */
    use HasFactory;
    use HasUuidv7;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'freelancer_profile_id',
        'skill_name',
        'proficiency_level',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proficiency_level' => SkillProficiencyLevel::class,
        ];
    }

    /**
     * @return BelongsTo<FreelancerProfile, $this>
     */
    public function freelancerProfile(): BelongsTo
    {
        return $this->belongsTo(FreelancerProfile::class);
    }
}
