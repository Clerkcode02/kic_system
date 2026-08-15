<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Actions;

use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Events\ProjectPublished;
use App\Domain\Freelance\Models\Project;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS §10/§19: a client publishes a project straight into Open — there is
 * no draft/review state ahead of it. §19 rules enforced here rather than
 * trusted from the FormRequest alone: budget > 0 and deadline in the
 * future (the FormRequest mirrors both for a fast 422, but this Action is
 * the source of truth, matching CreateBookingRequest's pattern).
 */
final class PublishProject implements Action
{
    /**
     * @param  array{category_id: string, title: string, description: string, budget_min: string|float, budget_max: string|float, deadline: string}  $data
     */
    public function handle(User $client, array $data): Project
    {
        $currency = 'CAD';
        $budgetMin = Money::fromDecimal((string) $data['budget_min'], $currency);
        $budgetMax = Money::fromDecimal((string) $data['budget_max'], $currency);
        $deadline = CarbonImmutable::parse($data['deadline']);

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

        if ($deadline->lt(CarbonImmutable::now()->startOfDay())) {
            throw ValidationException::withMessages([
                'deadline' => 'The deadline must be in the future.',
            ]);
        }

        return DB::transaction(function () use ($client, $data, $budgetMin, $budgetMax, $currency, $deadline) {
            $project = Project::create([
                'client_id' => $client->id,
                'category_id' => $data['category_id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'budget_min' => $budgetMin,
                'budget_max' => $budgetMax,
                'currency' => $currency,
                'deadline' => $deadline->toDateString(),
                'status' => ProjectStatus::Open,
            ]);

            ProjectPublished::dispatch($project);

            return $project;
        });
    }
}
