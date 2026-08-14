<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Freelance\Enums\ContractStatus;
use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Jobs\ScanDeliverableJob;
use App\Domain\Freelance\Models\Deliverable;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;

/**
 * Second half of the presigned upload flow (request URL → direct upload →
 * confirm here → queued virus scan). SRS §19: "no deliverables after
 * cancellation" — the milestone's contract/project must still be active.
 */
final class ConfirmDeliverable implements Action
{
    /**
     * @param  array{file_path: string, mime_type: string, size_bytes: int, description?: ?string}  $data
     */
    public function handle(Milestone $milestone, User $actor, array $data): Deliverable
    {
        $contract = $milestone->contract;

        if ($contract->status !== ContractStatus::Active || $contract->project->status !== ProjectStatus::InProgress) {
            throw new ConflictException(
                'Deliverables cannot be uploaded once the project is no longer in progress.',
                'project_not_active',
            );
        }

        if (! str_starts_with($data['file_path'], "deliverables/{$milestone->id}/")) {
            throw new ConflictException('The uploaded file does not belong to this milestone.', 'invalid_upload_path');
        }

        $deliverable = Deliverable::create([
            'milestone_id' => $milestone->id,
            'uploaded_by' => $actor->id,
            'file_path' => $data['file_path'],
            'mime_type' => $data['mime_type'],
            'size_bytes' => $data['size_bytes'],
            'description' => $data['description'] ?? null,
            'submitted_at' => now(),
            'scanned' => false,
        ]);

        ScanDeliverableJob::dispatch($deliverable);

        return $deliverable;
    }
}
