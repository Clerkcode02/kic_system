<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Queries;

use App\Domain\Freelance\Models\Project;

final class ProjectDetailQuery
{
    public function handle(string $projectId): Project
    {
        return Project::query()
            ->with([
                'category:id,name,slug',
                'client:id,name',
                'contract',
            ])
            ->findOrFail($projectId);
    }
}
