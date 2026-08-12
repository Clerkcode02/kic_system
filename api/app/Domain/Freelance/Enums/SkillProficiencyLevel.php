<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Enums;

enum SkillProficiencyLevel: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Expert = 'expert';
}
