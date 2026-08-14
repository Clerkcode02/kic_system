<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Events;

use App\Domain\Freelance\Models\Project;
use App\Support\Auditable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectPublished implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Project $project)
    {
    }
}
