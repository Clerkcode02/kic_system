<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

/**
 * Normalizes a free-text skill list so the `?skills[]=` filter can actually
 * match.
 *
 * Skills are free text on both sides of the marketplace
 * (`freelancer_skills.skill_name` and now `projects.required_skills`), so
 * without a single normalization rule "React", " react " and "REACT" are
 * three different skills and the filter silently returns nothing. This is
 * the one place that rule lives — both PublishProject and UpdateProject
 * route through it, and the filter normalizes its input the same way so a
 * query and a stored value can never disagree.
 *
 * Deliberately *not* stemming or aliasing: "React" and "ReactJS" stay
 * distinct. Collapsing those needs a controlled vocabulary, which is the
 * skills-reference-table design this column was chosen over.
 */
final class SkillList
{
    public const MAX_SKILLS = 20;

    public const MAX_SKILL_LENGTH = 50;

    /**
     * @param  array<int|string, mixed>  $skills
     * @return list<string>
     */
    public static function normalize(array $skills): array
    {
        $normalized = [];

        foreach ($skills as $skill) {
            if (! is_string($skill)) {
                continue;
            }

            $clean = self::normalizeOne($skill);

            if ($clean !== '') {
                $normalized[] = $clean;
            }
        }

        // array_values because array_unique preserves keys, and a jsonb
        // column with gaps in its keys serializes as an object, not an array.
        return array_values(array_unique($normalized));
    }

    public static function normalizeOne(string $skill): string
    {
        // Collapse internal whitespace too, so "machine   learning" and
        // "machine learning" are the same skill.
        $collapsed = preg_replace('/\s+/u', ' ', trim($skill)) ?? '';

        return mb_strtolower($collapsed);
    }
}
