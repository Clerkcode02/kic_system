<?php

declare(strict_types=1);

use App\Domain\Freelance\Actions\HireFreelancer;
use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Enums\ProposalStatus;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;

/**
 * HireFreelancer's lockForUpdate() guard only proves itself under a genuine
 * race between two separate DB connections — same reasoning as
 * ConcurrentBookingTest, and for the same reason this file manages its own
 * committed setup instead of RefreshDatabase, forking two real child
 * processes that each hire a *different* proposal on the *same* project at
 * the same time.
 */
it('lets exactly one of two truly concurrent hire requests for the same project succeed', function () {
    if (! function_exists('pcntl_fork')) {
        test()->markTestSkipped('pcntl extension is not available in this environment.');
    }

    app(RoleAndPermissionSeeder::class)->run();

    $client = User::factory()->customer()->create();
    $client->assignRole(RoleName::Customer->value);

    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    $freelancerA = User::factory()->freelancer()->create();
    $freelancerA->assignRole(RoleName::Freelancer->value);
    $profileA = FreelancerProfile::factory()->approved()->payoutsEnabled()->create(['user_id' => $freelancerA->id]);
    $proposalA = Proposal::factory()->submitted()->create([
        'project_id' => $project->id,
        'freelancer_id' => $profileA->id,
    ]);

    $freelancerB = User::factory()->freelancer()->create();
    $freelancerB->assignRole(RoleName::Freelancer->value);
    $profileB = FreelancerProfile::factory()->approved()->payoutsEnabled()->create(['user_id' => $freelancerB->id]);
    $proposalB = Proposal::factory()->submitted()->create([
        'project_id' => $project->id,
        'freelancer_id' => $profileB->id,
    ]);

    $resultFileA = tempnam(sys_get_temp_dir(), 'hire_a_');
    $resultFileB = tempnam(sys_get_temp_dir(), 'hire_b_');

    // Neither forked child may inherit the parent's live DB socket — each
    // reconnects independently right after the fork.
    DB::disconnect();

    $pidA = pcntl_fork();

    if ($pidA === -1) {
        test()->fail('Failed to fork process A.');
    }

    if ($pidA === 0) {
        attemptConcurrentHire($client->id, $proposalA->id, $resultFileA);
        exit(0);
    }

    $pidB = pcntl_fork();

    if ($pidB === -1) {
        test()->fail('Failed to fork process B.');
    }

    if ($pidB === 0) {
        attemptConcurrentHire($client->id, $proposalB->id, $resultFileB);
        exit(0);
    }

    pcntl_waitpid($pidA, $statusA);
    pcntl_waitpid($pidB, $statusB);

    DB::reconnect();

    $outcomeA = json_decode((string) file_get_contents($resultFileA), true)['outcome'] ?? null;
    $outcomeB = json_decode((string) file_get_contents($resultFileB), true)['outcome'] ?? null;

    @unlink($resultFileA);
    @unlink($resultFileB);

    $outcomes = [$outcomeA, $outcomeB];
    sort($outcomes);

    expect($outcomes)->toBe(['failed', 'succeeded']);
    expect($project->fresh()->status)->toBe(ProjectStatus::InProgress);
    expect(\App\Domain\Freelance\Models\Contract::query()->where('project_id', $project->id)->count())->toBe(1);

    // sort() has no defined ordering for backed-enum instances (they're
    // not Comparable) — sort by the underlying value instead, which is
    // what "Accepted, Rejected" actually means here.
    $statuses = [$proposalA->fresh()->status, $proposalB->fresh()->status];
    usort($statuses, fn (ProposalStatus $a, ProposalStatus $b) => $a->value <=> $b->value);
    expect($statuses)->toBe([ProposalStatus::Accepted, ProposalStatus::Rejected]);
});

function attemptConcurrentHire(string $clientId, string $proposalId, string $resultFile): void
{
    DB::reconnect();

    try {
        $client = User::query()->findOrFail($clientId);
        $proposal = Proposal::query()->findOrFail($proposalId);
        $contract = app(HireFreelancer::class)->handle($proposal, $client);
        file_put_contents($resultFile, json_encode(['outcome' => 'succeeded', 'contract_id' => $contract->id]));
    } catch (\App\Support\ConflictException) {
        file_put_contents($resultFile, json_encode(['outcome' => 'failed']));
    } catch (\Throwable $e) {
        file_put_contents($resultFile, json_encode(['outcome' => 'error', 'message' => $e->getMessage()]));
    }
}
