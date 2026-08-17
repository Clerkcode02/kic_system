<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Verification;

use App\Domain\User\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared shape for the four bulk verification endpoints (business
 * approve/reject, freelancer approve/reject) — each controller passes its
 * own permission via a route-bound check, so authorization here only
 * confirms the caller holds *some* verification-approval permission; the
 * per-item Action still enforces the pending-state guard.
 */
class BulkVerificationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(PermissionName::BusinessesApprove->value)
            || (bool) $this->user()?->can(PermissionName::FreelancersApprove->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $requiresReason = str_contains((string) $this->route()?->getName(), 'bulk-reject');

        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'uuid'],
            'reason' => $requiresReason
                ? ['required', 'string', 'min:10', 'max:1000']
                : ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
