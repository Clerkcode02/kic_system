<?php

declare(strict_types=1);

namespace App\Http\Requests\Proposal;

use App\Domain\Freelance\Queries\ListProposalsForProjectQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewProposals', $this->route('project')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Comparing proposals is the whole point of this list, so the
            // client picks the axis. An allow-list rather than a raw column
            // name — never interpolate a client-supplied sort into SQL.
            'sort' => ['sometimes', Rule::in(ListProposalsForProjectQuery::SORTS)],
            'cursor' => ['sometimes', 'string'],
        ];
    }
}
