<?php

declare(strict_types=1);

namespace App\Http\Requests\Dispute;

use App\Domain\Dispute\Models\Dispute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Dispute::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'disputable_type' => ['required', Rule::in(['booking', 'project', 'milestone', 'deliverable'])],
            'disputable_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
