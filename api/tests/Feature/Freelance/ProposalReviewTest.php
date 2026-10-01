<?php

declare(strict_types=1);

use App\Domain\Freelance\Enums\ProposalStatus;
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

// Local, uniquely-named helpers — CLAUDE.md §11.

function reviewClient(): User
{
    $user = User::factory()->customer()->create();
    $user->assignRole(RoleName::Customer->value);

    return $user;
}

function reviewFreelancer(float $rating = 0.0): User
{
    $user = User::factory()->freelancer()->create();
    $user->assignRole(RoleName::Freelancer->value);
    FreelancerProfile::factory()->approved()->payoutsEnabled()->create([
        'user_id' => $user->id,
        'rating_avg' => $rating,
    ]);

    return $user;
}

function proposalFrom(Project $project, User $freelancerUser, string $amount, int $days): Proposal
{
    return Proposal::factory()->create([
        'project_id' => $project->id,
        'freelancer_id' => $freelancerUser->freelancerProfile->id,
        'proposed_amount' => Money::fromDecimal($amount, 'CAD'),
        'delivery_days' => $days,
    ]);
}

// --- Listing and sorting ----------------------------------------------------

it('lists proposals for the owning client with freelancer details', function () {
    $client = reviewClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);
    $freelancer = reviewFreelancer(4.5);
    $proposal = proposalFrom($project, $freelancer, '800.00', 10);

    $this->withHeaders(authHeader($client))
        ->getJson("/api/v1/projects/{$project->id}/proposals")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $proposal->id)
        ->assertJsonPath('data.0.proposed_amount', '800.00')
        ->assertJsonPath('data.0.delivery_days', 10)
        ->assertJsonPath('data.0.freelancer.rating_avg', 4.5)
        ->assertJsonPath('data.0.freelancer.name', $freelancer->name);
});

it('sorts proposals by amount ascending', function () {
    $client = reviewClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    $expensive = proposalFrom($project, reviewFreelancer(), '2000.00', 5);
    $cheap = proposalFrom($project, reviewFreelancer(), '500.00', 20);

    $response = $this->withHeaders(authHeader($client))
        ->getJson("/api/v1/projects/{$project->id}/proposals?sort=amount_asc")
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$cheap->id, $expensive->id]);
});

it('sorts proposals by fastest delivery', function () {
    $client = reviewClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    $slow = proposalFrom($project, reviewFreelancer(), '900.00', 30);
    $fast = proposalFrom($project, reviewFreelancer(), '1200.00', 3);

    $response = $this->withHeaders(authHeader($client))
        ->getJson("/api/v1/projects/{$project->id}/proposals?sort=delivery_asc")
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$fast->id, $slow->id]);
});

it('sorts proposals by freelancer rating, which lives on another table', function () {
    $client = reviewClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    $low = proposalFrom($project, reviewFreelancer(2.0), '900.00', 10);
    $high = proposalFrom($project, reviewFreelancer(4.9), '900.00', 10);

    $response = $this->withHeaders(authHeader($client))
        ->getJson("/api/v1/projects/{$project->id}/proposals?sort=rating_desc")
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$high->id, $low->id]);

    // The join must not leak freelancer_profiles columns into the proposal
    // payload (select proposals.*).
    expect($response->json('data.0'))->not->toHaveKey('rating_avg');
});

it('rejects an unknown sort value rather than silently ignoring it', function () {
    $client = reviewClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    $this->withHeaders(authHeader($client))
        ->getJson("/api/v1/projects/{$project->id}/proposals?sort=proposed_amount")
        ->assertStatus(422)
        ->assertJsonValidationErrors('sort');
});

it('denies a competing freelancer from reading the proposal list', function () {
    $project = Project::factory()->open()->create(['client_id' => reviewClient()->id]);
    $competitor = reviewFreelancer();
    proposalFrom($project, $competitor, '900.00', 10);

    $this->withHeaders(authHeader($competitor))
        ->getJson("/api/v1/projects/{$project->id}/proposals")
        ->assertForbidden();
});

it('denies another client from reading someone else\'s proposal list', function () {
    $project = Project::factory()->open()->create(['client_id' => reviewClient()->id]);

    $this->withHeaders(authHeader(reviewClient()))
        ->getJson("/api/v1/projects/{$project->id}/proposals")
        ->assertForbidden();
});

// --- Shortlisting -----------------------------------------------------------

it('lets the owning client shortlist a proposal', function () {
    $client = reviewClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);
    $proposal = proposalFrom($project, reviewFreelancer(), '900.00', 10);

    $this->withHeaders(authHeader($client))
        ->postJson("/api/v1/proposals/{$proposal->id}/shortlist")
        ->assertOk()
        ->assertJsonPath('data.status', ProposalStatus::Shortlisted->value);
});

// --- Hiring -----------------------------------------------------------------

it('hires a proposal, creating a contract and rejecting the siblings', function () {
    $client = reviewClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    $winner = proposalFrom($project, reviewFreelancer(), '900.00', 10);
    $loser = proposalFrom($project, reviewFreelancer(), '1100.00', 14);

    $this->withHeaders(authHeader($client))
        ->postJson("/api/v1/proposals/{$winner->id}/hire")
        ->assertOk()
        ->assertJsonPath('data.project_id', $project->id);

    expect($winner->refresh()->status)->toBe(ProposalStatus::Accepted)
        ->and($loser->refresh()->status)->toBe(ProposalStatus::Rejected)
        ->and($project->refresh()->status->value)->toBe('in_progress');
});

it('returns 409 project_not_open when hiring a second time', function () {
    $client = reviewClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    $first = proposalFrom($project, reviewFreelancer(), '900.00', 10);
    $second = proposalFrom($project, reviewFreelancer(), '950.00', 12);

    $this->withHeaders(authHeader($client))
        ->postJson("/api/v1/proposals/{$first->id}/hire")
        ->assertOk();

    forgetAuthGuards();

    // The exact contract the hire UI has to render as its own state rather
    // than a generic error.
    $this->withHeaders(authHeader($client))
        ->postJson("/api/v1/proposals/{$second->id}/hire")
        ->assertStatus(409)
        ->assertJsonPath('code', 'project_not_open');
});

it('returns 403 freelancer_payouts_not_enabled when Stripe onboarding is incomplete', function () {
    $client = reviewClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    $unonboarded = User::factory()->freelancer()->create();
    $unonboarded->assignRole(RoleName::Freelancer->value);
    FreelancerProfile::factory()->approved()->create(['user_id' => $unonboarded->id]);

    $proposal = proposalFrom($project, $unonboarded, '900.00', 10);

    // 403, not 422 — PaymentsBlockedException renders as forbidden with a
    // machine-readable `code` the UI branches on.
    $this->withHeaders(authHeader($client))
        ->postJson("/api/v1/proposals/{$proposal->id}/hire")
        ->assertStatus(403)
        ->assertJsonPath('code', 'freelancer_payouts_not_enabled');

    expect($project->refresh()->status->value)->toBe('open');
});

it('denies a non-owning client from hiring', function () {
    $project = Project::factory()->open()->create(['client_id' => reviewClient()->id]);
    $proposal = proposalFrom($project, reviewFreelancer(), '900.00', 10);

    $this->withHeaders(authHeader(reviewClient()))
        ->postJson("/api/v1/proposals/{$proposal->id}/hire")
        ->assertForbidden();
});

it('rejects an unauthenticated hire attempt', function () {
    $project = Project::factory()->open()->create(['client_id' => reviewClient()->id]);
    $proposal = proposalFrom($project, reviewFreelancer(), '900.00', 10);

    $this->postJson("/api/v1/proposals/{$proposal->id}/hire")->assertUnauthorized();
});
