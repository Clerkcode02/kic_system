<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Support\ValueObjects\SkillList;
use Illuminate\Foundation\Http\FormRequest;

class IndexProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A browse UI that clears its skills input submits `?skills[]=`, which
     * arrives as a single empty entry. On a public browse endpoint that means
     * "no skills filter", not a validation error, so empty entries are
     * dropped before rules() sees them — and a list that was *only* empties
     * is removed entirely so `sometimes` treats it as absent.
     */
    protected function prepareForValidation(): void
    {
        $skills = $this->input('skills');

        if (! is_array($skills)) {
            return;
        }

        $present = array_values(array_filter(
            $skills,
            fn ($skill) => is_string($skill) && trim($skill) !== '',
        ));

        $this->merge($present === [] ? ['skills' => null] : ['skills' => $present]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'uuid', 'exists:categories,id'],
            'budget_min' => ['sometimes', 'numeric', 'gt:0'],
            'budget_max' => ['sometimes', 'numeric', 'gt:0'],
            // Nullable because prepareForValidation() collapses an
            // all-empty list to null; ListProjectsQuery skips the filter on
            // anything falsy.
            'skills' => ['sometimes', 'nullable', 'array', 'max:'.SkillList::MAX_SKILLS],
            'skills.*' => ['string', 'min:1', 'max:'.SkillList::MAX_SKILL_LENGTH],
            'cursor' => ['sometimes', 'string'],
        ];
    }
}
