<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMyProjectRequest extends FormRequest
{
    /**
     * Gated on `create`, not `view`. This collection is "projects I
     * published", so the meaningful ability is the one that lets the caller
     * publish at all — every role holds `projects.view` (browsing is public),
     * which would make a view-based check pass for freelancers and admins and
     * hand them a permanently empty list.
     *
     * No ownership check: the query is scoped to the caller, so there is no
     * other client's project to authorize against.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Project::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'cursor' => ['sometimes', 'string'],
        ];
    }
}
