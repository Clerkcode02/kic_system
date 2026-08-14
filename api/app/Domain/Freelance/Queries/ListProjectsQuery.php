<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Queries;

use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Models\Project;
use App\Support\ValueObjects\Money;
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
     * @param  array{category?: string, budget_min?: string|float, budget_max?: string|float, cursor?: string}  $filters
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

        return $query
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
