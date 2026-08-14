<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Events\ProjectScopeUpdated;
use App\Domain\Freelance\Models\Project;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;
use App\Support\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS §19: "scope edits after proposals exist trigger notifications to all
 * applicants." Editing is only meaningful before a hire locks the scope
 * into a Contract — once a project has left Open, its terms are what the
 * accepted Proposal/Contract already reflect.
 */
final class UpdateProject implements Action
{
    /**
     * @param  array{category_id?: string, title?: string, description?: string, budget_min?: string|float, budget_max?: string|float, deadline?: string}  $data
     */
    public function handle(Project $project, User $actor, array $data): Project
    {
        if ($project->status !== ProjectStatus::Open) {
            throw new ConflictException(
                'Only an open project can be edited.',
                'project_not_open',
            );
        }

        $currency = $project->currency;
        $budgetMin = isset($data['budget_min']) ? Money::fromDecimal((string) $data['budget_min'], $currency) : $project->budget_min;
        $budgetMax = isset($data['budget_max']) ? Money::fromDecimal((string) $data['budget_max'], $currency) : $project->budget_max;
        $deadline = isset($data['deadline']) ? CarbonImmutable::parse($data['deadline']) : null;

        if ($budgetMin->minorUnits <= 0) {
            throw ValidationException::withMessages([
                'budget_min' => 'The minimum budget must be greater than zero.',
            ]);
        }

        if ($budgetMax->minorUnits < $budgetMin->minorUnits) {
            throw ValidationException::withMessages([
                'budget_max' => 'The maximum budget must be at least the minimum budget.',
            ]);
        }

        if ($deadline !== null && $deadline->lt(CarbonImmutable::now()->startOfDay())) {
            throw ValidationException::withMessages([
                'deadline' => 'The deadline must be in the future.',
            ]);
        }

        return DB::transaction(function () use ($project, $actor, $data, $budgetMin, $budgetMax, $deadline) {
            $before = $project->only(['category_id', 'title', 'description', 'deadline', 'status']);

            $project->update([
                'category_id' => $data['category_id'] ?? $project->category_id,
                'title' => $data['title'] ?? $project->title,
                'description' => $data['description'] ?? $project->description,
                'budget_min' => $budgetMin,
                'budget_max' => $budgetMax,
                'deadline' => $deadline?->toDateString() ?? $project->deadline,
            ]);

            $affectedFreelancerUserIds = $project->proposals()
                ->with('freelancer:id,user_id')
                ->get()
                ->pluck('freelancer.user_id')
                ->unique()
                ->values()
                ->all();

            AuditLog::create([
                'actor_id' => $actor->id,
                'action' => 'project.updated',
                'auditable_type' => 'project',
                'auditable_id' => $project->id,
                'before_state' => $before,
                'after_state' => $project->refresh()->only(['category_id', 'title', 'description', 'deadline', 'status']),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            if ($affectedFreelancerUserIds !== []) {
                ProjectScopeUpdated::dispatch($project, $affectedFreelancerUserIds);
            }

            return $project;
        });
    }
}
