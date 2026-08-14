<?php

declare(strict_types=1);

use App\Domain\Freelance\Enums\ContractStatus;
use App\Domain\Freelance\Enums\MilestoneStatus;
use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Jobs\ScanDeliverableJob;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\User\Models\User;
use App\Support\ValueObjects\Money;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

/**
 * @return array{0: Contract, 1: User, 2: User}
 */
function activeContract(string $total = '900.00'): array
{
    $client = User::factory()->customer()->create();
    $client->assignRole(\App\Domain\User\Enums\RoleName::Customer->value);

    $freelancerUser = User::factory()->freelancer()->create();
    $freelancerUser->assignRole(\App\Domain\User\Enums\RoleName::Freelancer->value);
    $freelancerProfile = FreelancerProfile::factory()->approved()->create(['user_id' => $freelancerUser->id]);

    $project = Project::factory()->inProgress()->create(['client_id' => $client->id]);

    $proposal = Proposal::factory()->accepted()->create([
        'project_id' => $project->id,
        'freelancer_id' => $freelancerProfile->id,
        'proposed_amount' => Money::fromDecimal($total, 'CAD'),
    ]);

    $contract = Contract::factory()->active()->create([
        'project_id' => $project->id,
        'proposal_id' => $proposal->id,
        'total_amount' => Money::fromDecimal($total, 'CAD'),
    ]);

    return [$contract, $client, $freelancerUser];
}

// --- Defining milestones ----------------------------------------------------

it('lets the hiring client define milestones that sum to the contract total', function () {
    [$contract, $client] = activeContract('900.00');

    $this->withHeaders(authHeader($client))
        ->postJson("/api/v1/contracts/{$contract->id}/milestones", [
            'milestones' => [
                ['title' => 'Design', 'amount' => '300.00', 'due_date' => now()->addWeek()->toDateString()],
                ['title' => 'Build', 'amount' => '600.00', 'due_date' => now()->addWeeks(3)->toDateString()],
            ],
        ])
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($contract->milestones()->count())->toBe(2);
});

it('rejects milestone amounts that do not sum to the contract total', function () {
    [$contract, $client] = activeContract('900.00');

    $this->withHeaders(authHeader($client))
        ->postJson("/api/v1/contracts/{$contract->id}/milestones", [
            'milestones' => [
                ['title' => 'Design', 'amount' => '300.00', 'due_date' => now()->addWeek()->toDateString()],
                ['title' => 'Build', 'amount' => '500.00', 'due_date' => now()->addWeeks(3)->toDateString()],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['milestones']);

    expect($contract->milestones()->count())->toBe(0);
});

it('denies a non-owning client from defining milestones', function () {
    [$contract] = activeContract();
    $stranger = User::factory()->customer()->create();
    $stranger->assignRole(\App\Domain\User\Enums\RoleName::Customer->value);

    $this->withHeaders(authHeader($stranger))
        ->postJson("/api/v1/contracts/{$contract->id}/milestones", [
            'milestones' => [
                ['title' => 'Design', 'amount' => '900.00', 'due_date' => now()->addWeek()->toDateString()],
            ],
        ])
        ->assertForbidden();
});

it('blocks redefining milestones once one has progressed past pending', function () {
    [$contract, $client] = activeContract('900.00');
    Milestone::factory()->submitted()->create(['contract_id' => $contract->id, 'amount' => Money::fromDecimal('900.00', 'CAD')]);

    $this->withHeaders(authHeader($client))
        ->postJson("/api/v1/contracts/{$contract->id}/milestones", [
            'milestones' => [
                ['title' => 'Redo', 'amount' => '900.00', 'due_date' => now()->addWeek()->toDateString()],
            ],
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'milestones_locked');
});

// --- Submit / approve / reject -----------------------------------------------

it('lets the hired freelancer submit a milestone with deliverables', function () {
    Queue::fake();
    [$contract, , $freelancerUser] = activeContract();
    $milestone = Milestone::factory()->pending()->create(['contract_id' => $contract->id]);

    $uploadUrl = $this->withHeaders(authHeader($freelancerUser))
        ->postJson("/api/v1/milestones/{$milestone->id}/deliverables/upload-url", ['filename' => 'work.zip'])
        ->assertOk();

    $path = $uploadUrl->json('data.path');
    expect($path)->toStartWith("deliverables/{$milestone->id}/");

    forgetAuthGuards();
    $confirm = $this->withHeaders(authHeader($freelancerUser))
        ->postJson("/api/v1/milestones/{$milestone->id}/deliverables", [
            'file_path' => $path,
            'mime_type' => 'application/zip',
            'size_bytes' => 1024,
        ])
        ->assertCreated()
        ->assertJsonPath('data.scanned', false);

    Queue::assertPushed(ScanDeliverableJob::class);

    $deliverableId = $confirm->json('data.id');

    forgetAuthGuards();
    $this->withHeaders(authHeader($freelancerUser))
        ->postJson("/api/v1/milestones/{$milestone->id}/submit", ['deliverable_ids' => [$deliverableId]])
        ->assertOk()
        ->assertJsonPath('data.status', MilestoneStatus::Submitted->value);
});

it('blocks approval by anyone other than the hiring client', function () {
    [$contract, , $freelancerUser] = activeContract();
    $milestone = Milestone::factory()->submitted()->create(['contract_id' => $contract->id]);

    $this->withHeaders(authHeader($freelancerUser))
        ->postJson("/api/v1/milestones/{$milestone->id}/approve")
        ->assertForbidden();

    $stranger = User::factory()->customer()->create();
    $stranger->assignRole(\App\Domain\User\Enums\RoleName::Customer->value);

    forgetAuthGuards();
    $this->withHeaders(authHeader($stranger))
        ->postJson("/api/v1/milestones/{$milestone->id}/approve")
        ->assertForbidden();

    expect($milestone->fresh()->status)->toBe(MilestoneStatus::Submitted);
});

it('lets the hiring client approve a submitted milestone', function () {
    Queue::fake();
    [$contract, $client] = activeContract();
    $milestone = Milestone::factory()->submitted()->create(['contract_id' => $contract->id]);

    $this->withHeaders(authHeader($client))
        ->postJson("/api/v1/milestones/{$milestone->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', MilestoneStatus::Approved->value);

    Queue::assertPushed(\App\Domain\Payment\Jobs\ReleaseMilestoneEscrowJob::class);
});

it('rejects a submitted milestone to disputed, and lets the freelancer resubmit it', function () {
    [$contract, $client, $freelancerUser] = activeContract();
    $milestone = Milestone::factory()->submitted()->create(['contract_id' => $contract->id]);
    $deliverable = \App\Domain\Freelance\Models\Deliverable::factory()->create(['milestone_id' => $milestone->id]);

    $this->withHeaders(authHeader($client))
        ->postJson("/api/v1/milestones/{$milestone->id}/reject", ['reason' => 'Missing the login flow.'])
        ->assertOk()
        ->assertJsonPath('data.status', MilestoneStatus::Disputed->value)
        ->assertJsonPath('data.rejection_reason', 'Missing the login flow.');

    forgetAuthGuards();
    $this->withHeaders(authHeader($freelancerUser))
        ->postJson("/api/v1/milestones/{$milestone->id}/submit", ['deliverable_ids' => [$deliverable->id]])
        ->assertOk()
        ->assertJsonPath('data.status', MilestoneStatus::Submitted->value)
        ->assertJsonPath('data.rejection_reason', null);
});

// --- Contract completion ------------------------------------------------------

it('completes the contract and project only once every milestone reaches paid', function () {
    [$contract, , ] = activeContract('900.00');
    $first = Milestone::factory()->approved()->create(['contract_id' => $contract->id, 'amount' => Money::fromDecimal('300.00', 'CAD')]);
    $second = Milestone::factory()->approved()->create(['contract_id' => $contract->id, 'amount' => Money::fromDecimal('600.00', 'CAD')]);

    $completeContract = app(\App\Domain\Freelance\Actions\CompleteContract::class);

    $completeContract->handle($first);

    expect($contract->fresh()->status)->toBe(ContractStatus::Active);

    $completeContract->handle($second);

    expect($contract->fresh()->status)->toBe(ContractStatus::Completed)
        ->and($contract->project->fresh()->status)->toBe(ProjectStatus::Completed);
});

it('rejects unauthenticated requests to every write endpoint', function () {
    [$contract] = activeContract();
    $milestone = Milestone::factory()->pending()->create(['contract_id' => $contract->id]);

    $this->postJson("/api/v1/contracts/{$contract->id}/milestones", [])->assertUnauthorized();
    $this->postJson("/api/v1/milestones/{$milestone->id}/submit", [])->assertUnauthorized();
    $this->postJson("/api/v1/milestones/{$milestone->id}/approve")->assertUnauthorized();
    $this->postJson("/api/v1/milestones/{$milestone->id}/reject", [])->assertUnauthorized();
});
