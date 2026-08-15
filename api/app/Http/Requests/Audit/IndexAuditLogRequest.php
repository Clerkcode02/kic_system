<?php

declare(strict_types=1);

namespace App\Http\Requests\Audit;

use App\Domain\Audit\Models\AuditLog;
use Illuminate\Foundation\Http\FormRequest;

class IndexAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', AuditLog::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'actor' => ['sometimes', 'uuid'],
            'action' => ['sometimes', 'string'],
            'entity' => ['sometimes', 'string'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
            'cursor' => ['sometimes', 'string'],
        ];
    }
}
