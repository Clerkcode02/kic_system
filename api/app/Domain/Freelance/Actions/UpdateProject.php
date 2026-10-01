<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Events\ProjectScopeUpdated;
use App\Domain\Freelance\Models\Project;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\SkillList;
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
     * @param  array{category_id?: string, title?: string, description?: string, budget_min?: string|float, budget_max?: string|float, deadline?: string, required_skills?: array<int, string>}  $data
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

        // Absent key means "leave as-is"; an explicitly empty array means
        // "clear the skills", which is why this distinguishes the two rather
        // than treating [] as absent.
        $requiredSkills = array_key_exists('required_skills', $data)
            ? SkillList::normalize($data['required_skills'])
            : null;

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

        return DB::transaction(function () use ($project, $actor, $data, $budgetMin, $budgetMax, $deadline, $requiredSkills) {
            $before = $project->only(['category_id', 'title', 'description', 'deadline', 'required_skills', 'status']);

            $project->update([
                'category_id' => $data['category_id'] ?? $project->category_id,
                'title' => $data['title'] ?? $project->title,
                'description' => $data['description'] ?? $project->description,
                'budget_min' => $budgetMin,
                'budget_max' => $budgetMax,
                'deadline' => $deadline?->toDateString() ?? $project->deadline,
                'required_skills' => $requiredSkills === null
                    ? $project->required_skills
                    : ($requiredSkills === [] ? null : $requiredSkills),
            ]);

            $affectedFreelancerUserIds = $project->proposals()
                ->with('freelancer:id,user_id')
                ->get()
                ->pluck('freelancer.user_id')
                ->unique()
                ->values()
                ->all();

            $after = $project->refresh()->only(['category_id', 'title', 'description', 'deadline', 'required_skills', 'status']);

            // Always dispatched (for the audit trail — see
            // ProjectScopeUpdated::auditAction()); the notification fan-out
            // to affected freelancers a future listener would add is what's
            // actually conditional on $affectedFreelancerUserIds being
            // non-empty (SRS §19 "scope edits after proposals exist trigger
            // notifications to all applicants").
            ProjectScopeUpdated::dispatch($project, $affectedFreelancerUserIds, $actor, $before, $after);

            return $project;
        });
    }
}
