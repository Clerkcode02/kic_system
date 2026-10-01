<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Queries;

use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Models\Project;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\SkillList;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * GET /projects?category=&budget_min=&budget_max=&cursor= — public browsing
 * (CLAUDE.md §4: "Browse services / projects" is ✅ for every role,
 * including anonymous visitors, matching how ServiceListQuery is exposed).
 * Only Open projects are listed — a project mid-contract or finished isn't
 * something a freelancer can still propose against.
 */
final class ListProjectsQuery
{
    private const PER_PAGE = 20;

    /**
     * @param  array{category?: string, budget_min?: string|float, budget_max?: string|float, skills?: array<int, string>, cursor?: string}  $filters
     * @return CursorPaginator<int, Project>
     */
    public function handle(array $filters): CursorPaginator
    {
        $query = Project::query()
            ->with('category:id,name,slug')
            ->where('status', ProjectStatus::Open);

        if (! empty($filters['category'])) {
            $query->where('category_id', $filters['category']);
        }

        if (! empty($filters['budget_min'])) {
            $query->where('budget_max', '>=', Money::fromDecimal((string) $filters['budget_min'], 'CAD')->toDecimal());
        }

        if (! empty($filters['budget_max'])) {
            $query->where('budget_min', '<=', Money::fromDecimal((string) $filters['budget_max'], 'CAD')->toDecimal());
        }

        // Matches a project that requires *any* of the requested skills, not
        // all of them: a freelancer filtering on "react" and "vue" is saying
        // what they can do, not demanding both appear on one project.
        // Normalized through SkillList so the query and the stored value
        // agree on casing/spacing.
        //
        // `??|` is the escaped form of Postgres's jsonb `?|` ("has any of
        // these keys") — a literal `?` in raw SQL would be swallowed as a
        // PDO placeholder, so Laravel's grammar unescapes `??` back to one
        // `?`. This is the operator the GIN index on required_skills serves;
        // the function form jsonb_exists_any() would not be index-eligible.
        if (! empty($filters['skills'])) {
            $skills = SkillList::normalize($filters['skills']);

            if ($skills !== []) {
                $placeholders = implode(',', array_fill(0, count($skills), '?'));
                $query->whereRaw("required_skills ??| array[{$placeholders}]", $skills);
            }
        }

        return $query
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
