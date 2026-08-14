<?php

declare(strict_types=1);

use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Enums\ProposalStatus;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function projectClient(): User
{
    $user = User::factory()->customer()->create();
    $user->assignRole(RoleName::Customer->value);

    return $user;
}

function approvedFreelancer(): User
{
    $user = User::factory()->freelancer()->create();
    $user->assignRole(RoleName::Freelancer->value);
    FreelancerProfile::factory()->approved()->payoutsEnabled()->create(['user_id' => $user->id]);

    return $user;
}

function pendingFreelancer(): User
{
    $user = User::factory()->freelancer()->create();
    $user->assignRole(RoleName::Freelancer->value);
    FreelancerProfile::factory()->pending()->create(['user_id' => $user->id]);

    return $user;
}

function futureDeadline(): string
{
    return now()->addMonth()->toDateString();
}

function projectPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => \App\Domain\Catalog\Models\Category::factory()->create()->id,
        'title' => 'Build a marketing site',
        'description' => 'Full description of the work needed.',
        'budget_min' => '500.00',
        'budget_max' => '1000.00',
        'deadline' => futureDeadline(),
    ], $overrides);
}

// --- Publishing -------------------------------------------------------------

it('lets a customer publish a project', function () {
    $client = projectClient();

    $this->withHeaders(authHeader($client))
        ->postJson('/api/v1/projects', projectPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', ProjectStatus::Open->value)
        ->assertJsonPath('data.budget_min', '500.00')
        ->assertJsonPath('data.budget_max', '1000.00');
});

it('rejects a project with a max budget below the min budget', function () {
    $client = projectClient();

    $this->withHeaders(authHeader($client))
        ->postJson('/api/v1/projects', projectPayload(['budget_min' => '1000.00', 'budget_max' => '500.00']))
        ->assertStatus(422);
});

it('rejects a project with a deadline in the past', function () {
    $client = projectClient();

    $this->withHeaders(authHeader($client))
        ->postJson('/api/v1/projects', projectPayload(['deadline' => now()->subDay()->toDateString()]))
        ->assertStatus(422);
});

// --- Updating -----------------------------------------------------------------

it('lets the owning client update an open project', function () {
    $client = projectClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    $this->withHeaders(authHeader($client))
        ->patchJson("/api/v1/projects/{$project->id}", ['title' => 'Updated title'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated title');
});

it('blocks editing a project once it is no longer open', function () {
    $client = projectClient();
    $project = Project::factory()->inProgress()->create(['client_id' => $client->id]);

    $this->withHeaders(authHeader($client))
        ->patchJson("/api/v1/projects/{$project->id}", ['title' => 'New title'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'project_not_open');
});

it('denies a stranger from updating someone else\'s project', function () {
    $client = projectClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);
    $stranger = projectClient();

    $this->withHeaders(authHeader($stranger))
        ->patchJson("/api/v1/projects/{$project->id}", ['title' => 'Hijacked'])
        ->assertForbidden();
});

// --- Cancelling -----------------------------------------------------------------

it('lets a client cancel an open project', function () {
    $client = projectClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    $this->withHeaders(authHeader($client))
        ->deleteJson("/api/v1/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.status', ProjectStatus::Cancelled->value);
});

it('blocks a non-admin client from cancelling a project with an active contract', function () {
    $client = projectClient();
    $project = Project::factory()->inProgress()->create(['client_id' => $client->id]);

    $this->withHeaders(authHeader($client))
        ->deleteJson("/api/v1/projects/{$project->id}")
        ->assertStatus(409)
        ->assertJsonPath('code', 'exceptional_cancellation_requires_admin');
});

// --- Proposals ----------------------------------------------------------------

it('lets an approved freelancer submit a proposal to an open project', function () {
    $client = projectClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);
    $freelancer = approvedFreelancer();

    $this->withHeaders(authHeader($freelancer))
        ->postJson("/api/v1/projects/{$project->id}/proposals", [
            'proposed_amount' => '750.00',
            'cover_letter' => 'I would love to help with this.',
            'delivery_days' => 14,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', ProposalStatus::Submitted->value);
});

it('blocks a pending (not-yet-approved) freelancer from submitting a proposal', function () {
    $client = projectClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);
    $freelancer = pendingFreelancer();

    $this->withHeaders(authHeader($freelancer))
        ->postJson("/api/v1/projects/{$project->id}/proposals", [
            'proposed_amount' => '750.00',
            'cover_letter' => 'Let me help.',
            'delivery_days' => 14,
        ])
        ->assertForbidden();
});

it('returns 422 for a second proposal from the same freelancer on the same project', function () {
    $client = projectClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);
    $freelancer = approvedFreelancer();

    $payload = [
        'proposed_amount' => '750.00',
        'cover_letter' => 'First attempt.',
        'delivery_days' => 14,
    ];

    $this->withHeaders(authHeader($freelancer))
        ->postJson("/api/v1/projects/{$project->id}/proposals", $payload)
        ->assertCreated();

    forgetAuthGuards();
    $this->withHeaders(authHeader($freelancer))
        ->postJson("/api/v1/projects/{$project->id}/proposals", $payload)
        ->assertStatus(422);
});

it('rejects a proposal to a project that is no longer open', function () {
    $client = projectClient();
    $project = Project::factory()->inProgress()->create(['client_id' => $client->id]);
    $freelancer = approvedFreelancer();

    $this->withHeaders(authHeader($freelancer))
        ->postJson("/api/v1/projects/{$project->id}/proposals", [
            'proposed_amount' => '750.00',
            'cover_letter' => 'Too late.',
            'delivery_days' => 14,
        ])
        ->assertStatus(422);
});

it('lets the client shortlist and the freelancer withdraw a proposal', function () {
    $client = projectClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);
    $freelancerUser = approvedFreelancer();
    $freelancerProfile = FreelancerProfile::query()->where('user_id', $freelancerUser->id)->firstOrFail();
    $proposal = Proposal::factory()->submitted()->create([
        'project_id' => $project->id,
        'freelancer_id' => $freelancerProfile->id,
    ]);

    $this->withHeaders(authHeader($client))
        ->postJson("/api/v1/proposals/{$proposal->id}/shortlist")
        ->assertOk()
        ->assertJsonPath('data.status', ProposalStatus::Shortlisted->value);

    forgetAuthGuards();
    $this->withHeaders(authHeader($freelancerUser))
        ->postJson("/api/v1/proposals/{$proposal->id}/withdraw")
        ->assertOk()
        ->assertJsonPath('data.status', ProposalStatus::Withdrawn->value);
});

it('lists proposals scoped to the owning client and rejects a competing freelancer', function () {
    $client = projectClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);
    $freelancerUser = approvedFreelancer();
    $freelancerProfile = FreelancerProfile::query()->where('user_id', $freelancerUser->id)->firstOrFail();
    Proposal::factory()->submitted()->create([
        'project_id' => $project->id,
        'freelancer_id' => $freelancerProfile->id,
    ]);

    $this->withHeaders(authHeader($client))
        ->getJson("/api/v1/projects/{$project->id}/proposals")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    forgetAuthGuards();
    [$otherFreelancer] = [approvedFreelancer()];
    $this->withHeaders(authHeader($otherFreelancer))
        ->getJson("/api/v1/projects/{$project->id}/proposals")
        ->assertForbidden();
});

it('lists a freelancer\'s own proposals across projects', function () {
    $freelancerUser = approvedFreelancer();
    $freelancerProfile = FreelancerProfile::query()->where('user_id', $freelancerUser->id)->firstOrFail();
    Proposal::factory()->count(2)->submitted()->create(['freelancer_id' => $freelancerProfile->id]);

    $this->withHeaders(authHeader($freelancerUser))
        ->getJson('/api/v1/freelancers/me/proposals')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

// --- Hiring ---------------------------------------------------------------------

it('hires a proposal: creates a contract, moves the project to in_progress, and auto-rejects the rest', function () {
    $client = projectClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    $winnerUser = approvedFreelancer();
    $winnerProfile = FreelancerProfile::query()->where('user_id', $winnerUser->id)->firstOrFail();
    $winningProposal = Proposal::factory()->submitted()->create([
        'project_id' => $project->id,
        'freelancer_id' => $winnerProfile->id,
        'proposed_amount' => \App\Support\ValueObjects\Money::fromDecimal('800.00', 'CAD'),
    ]);

    $loserUser = approvedFreelancer();
    $loserProfile = FreelancerProfile::query()->where('user_id', $loserUser->id)->firstOrFail();
    $losingProposal = Proposal::factory()->submitted()->create([
        'project_id' => $project->id,
        'freelancer_id' => $loserProfile->id,
    ]);

    $this->withHeaders(authHeader($client))
        ->postJson("/api/v1/proposals/{$winningProposal->id}/hire")
        ->assertOk()
        ->assertJsonPath('data.total_amount', '800.00')
        ->assertJsonPath('data.status', 'active');

    expect($project->fresh()->status)->toBe(ProjectStatus::InProgress);
    expect($winningProposal->fresh()->status)->toBe(ProposalStatus::Accepted);
    expect($losingProposal->fresh()->status)->toBe(ProposalStatus::Rejected);
});

it('blocks hiring a proposal on a project that is no longer open', function () {
    $client = projectClient();
    $project = Project::factory()->inProgress()->create(['client_id' => $client->id]);
    $freelancerUser = approvedFreelancer();
    $freelancerProfile = FreelancerProfile::query()->where('user_id', $freelancerUser->id)->firstOrFail();
    $proposal = Proposal::factory()->submitted()->create([
        'project_id' => $project->id,
        'freelancer_id' => $freelancerProfile->id,
    ]);

    $this->withHeaders(authHeader($client))
        ->postJson("/api/v1/proposals/{$proposal->id}/hire")
        ->assertStatus(409)
        ->assertJsonPath('code', 'project_not_open');
});

it('denies a non-owning client from hiring a proposal', function () {
    $client = projectClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);
    $freelancerUser = approvedFreelancer();
    $freelancerProfile = FreelancerProfile::query()->where('user_id', $freelancerUser->id)->firstOrFail();
    $proposal = Proposal::factory()->submitted()->create([
        'project_id' => $project->id,
        'freelancer_id' => $freelancerProfile->id,
    ]);

    $stranger = projectClient();

    $this->withHeaders(authHeader($stranger))
        ->postJson("/api/v1/proposals/{$proposal->id}/hire")
        ->assertForbidden();
});

it('rejects unauthenticated requests to every write endpoint', function () {
    $client = projectClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    $this->postJson('/api/v1/projects', projectPayload())->assertUnauthorized();
    $this->patchJson("/api/v1/projects/{$project->id}", ['title' => 'x'])->assertUnauthorized();
    $this->deleteJson("/api/v1/projects/{$project->id}")->assertUnauthorized();
    $this->postJson("/api/v1/projects/{$project->id}/proposals", [])->assertUnauthorized();
    $this->getJson('/api/v1/freelancers/me/proposals')->assertUnauthorized();
});
