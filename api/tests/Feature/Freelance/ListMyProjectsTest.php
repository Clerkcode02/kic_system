<?php

declare(strict_types=1);

use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use App\Support\ValueObjects\Money;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

/*
 * Helpers are local to this file on purpose. CLAUDE.md §11 requires any
 * fixture helper shared across test files to live in tests/Pest.php, because
 * a function declared in one file is invisible to another under --parallel.
 * These names are unique to this file, so nothing here depends on load order.
 */

function myProjectsClient(): User
{
    $user = User::factory()->customer()->create();
    $user->assignRole(RoleName::Customer->value);

    return $user;
}

function myProjectsFreelancer(): User
{
    $user = User::factory()->freelancer()->create();
    $user->assignRole(RoleName::Freelancer->value);
    FreelancerProfile::factory()->approved()->create(['user_id' => $user->id]);

    return $user;
}

// --- GET /v1/me/projects ----------------------------------------------------

it('lists the calling client\'s projects in every status', function () {
    $client = myProjectsClient();

    $open = Project::factory()->open()->create(['client_id' => $client->id]);
    $inProgress = Project::factory()->inProgress()->create(['client_id' => $client->id]);
    $completed = Project::factory()->completed()->create(['client_id' => $client->id]);
    $cancelled = Project::factory()->cancelled()->create(['client_id' => $client->id]);

    $response = $this->withHeaders(authHeader($client))
        ->getJson('/api/v1/me/projects')
        ->assertOk();

    // The whole point of this endpoint: the public list shows only Open, so
    // the other three statuses are exactly what was previously unreachable.
    expect(collect($response->json('data'))->pluck('id')->sort()->values()->all())
        ->toEqualCanonicalizing([$open->id, $inProgress->id, $completed->id, $cancelled->id]);
});

it('excludes projects belonging to another client', function () {
    $client = myProjectsClient();
    $mine = Project::factory()->open()->create(['client_id' => $client->id]);

    $stranger = myProjectsClient();
    Project::factory()->open()->create(['client_id' => $stranger->id]);

    $this->withHeaders(authHeader($client))
        ->getJson('/api/v1/me/projects')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);
});

it('filters the list by status', function () {
    $client = myProjectsClient();

    Project::factory()->open()->create(['client_id' => $client->id]);
    $inProgress = Project::factory()->inProgress()->create(['client_id' => $client->id]);

    $this->withHeaders(authHeader($client))
        ->getJson('/api/v1/me/projects?status='.ProjectStatus::InProgress->value)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $inProgress->id);
});

it('rejects an unknown status with a 422', function () {
    $client = myProjectsClient();

    $this->withHeaders(authHeader($client))
        ->getJson('/api/v1/me/projects?status=archived')
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('includes a proposal count so the list can be rendered without an N+1', function () {
    $client = myProjectsClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    Proposal::factory()->count(2)->create(['project_id' => $project->id]);

    $this->withHeaders(authHeader($client))
        ->getJson('/api/v1/me/projects')
        ->assertOk()
        ->assertJsonPath('data.0.proposals_count', 2);
});

it('does not expose a proposal count on the public browse list', function () {
    $project = Project::factory()->open()->create();
    Proposal::factory()->create(['project_id' => $project->id]);

    $this->getJson('/api/v1/projects')
        ->assertOk()
        ->assertJsonMissingPath('data.0.proposals_count');
});

it('requires authentication', function () {
    $this->getJson('/api/v1/me/projects')->assertUnauthorized();
});

it('denies a freelancer, who has no projects of their own to list', function () {
    $freelancer = myProjectsFreelancer();

    $this->withHeaders(authHeader($freelancer))
        ->getJson('/api/v1/me/projects')
        ->assertForbidden();
});

// --- GET /v1/me/contracts ---------------------------------------------------

it('lists contracts for the client side of the project', function () {
    $client = myProjectsClient();
    $project = Project::factory()->inProgress()->create(['client_id' => $client->id]);
    $contract = Contract::factory()->create(['project_id' => $project->id]);

    $this->withHeaders(authHeader($client))
        ->getJson('/api/v1/me/contracts')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $contract->id);
});

it('lists contracts for the hired freelancer side of the project', function () {
    $freelancer = myProjectsFreelancer();
    $project = Project::factory()->inProgress()->create();

    $proposal = Proposal::factory()->accepted()->create([
        'project_id' => $project->id,
        'freelancer_id' => $freelancer->freelancerProfile->id,
        'proposed_amount' => Money::fromDecimal('900.00', 'CAD'),
    ]);

    $contract = Contract::factory()->create([
        'project_id' => $project->id,
        'proposal_id' => $proposal->id,
    ]);

    $this->withHeaders(authHeader($freelancer))
        ->getJson('/api/v1/me/contracts')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $contract->id);
});

it('excludes a contract the caller is not a party to', function () {
    $client = myProjectsClient();
    Contract::factory()->create(['project_id' => Project::factory()->inProgress()->create()->id]);

    $this->withHeaders(authHeader($client))
        ->getJson('/api/v1/me/contracts')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('requires authentication for the contract list', function () {
    $this->getJson('/api/v1/me/contracts')->assertUnauthorized();
});

it('keeps serving the existing freelancer contract route', function () {
    $freelancer = myProjectsFreelancer();
    $project = Project::factory()->inProgress()->create();

    $proposal = Proposal::factory()->accepted()->create([
        'project_id' => $project->id,
        'freelancer_id' => $freelancer->freelancerProfile->id,
    ]);

    $contract = Contract::factory()->create([
        'project_id' => $project->id,
        'proposal_id' => $proposal->id,
    ]);

    $this->withHeaders(authHeader($freelancer))
        ->getJson('/api/v1/freelancer/me/contracts')
        ->assertOk()
        ->assertJsonPath('data.0.id', $contract->id);
});
