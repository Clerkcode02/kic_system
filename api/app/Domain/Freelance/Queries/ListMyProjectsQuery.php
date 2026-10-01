<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Queries;

use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Models\Project;
use App\Domain\User\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * GET /me/projects — the projects the caller published, in every status.
 *
 * Deliberately separate from ListProjectsQuery rather than a branch inside
 * it: that query is the *public* browse surface and hard-filters to Open, so
 * widening it would leak a client's cancelled or in-progress work into
 * anonymous browsing. The two have the same shape but opposite audiences.
 *
 * Without this query a client loses sight of their own project the moment
 * they hire someone and it leaves Open — nothing else in the API lists it.
 */
final class ListMyProjectsQuery
{
    private const PER_PAGE = 20;

    /**
     * @param  array{status?: string, cursor?: string}  $filters
     * @return CursorPaginator<int, Project>
     */
    public function handle(User $client, array $filters = []): CursorPaginator
    {
        $query = Project::query()
            ->where('client_id', $client->id)
            ->with(['category:id,name,slug', 'contract'])
            // Lets the list render "5 proposals" per card without an N+1.
            // Exposed through ProjectListResource::whenCounted, so callers
            // that don't count simply omit the key.
            ->withCount('proposals');

        if (! empty($filters['status'])) {
            $query->where('status', ProjectStatus::from($filters['status']));
        }

        return $query
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
