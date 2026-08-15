<?php

declare(strict_types=1);

use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use App\Support\ValueObjects\Money;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

if (! function_exists('authHeader')) {
    /**
     * @return array<string, string>
     */
    function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }
}

/**
 * @return array{0: User, 1: FreelancerProfile}
 */
function freelancerDashboardOwner(): array
{
    $user = User::factory()->freelancer()->create();
    $user->assignRole(RoleName::Freelancer->value);
    $profile = FreelancerProfile::factory()->approved()->create(['user_id' => $user->id]);

    return [$user, $profile];
}

/**
 * @return array{0: Contract, 1: FreelancerProfile}
 */
function hiredContractFor(User $freelancerUser, FreelancerProfile $profile, string $total = '900.00'): array
{
    $client = User::factory()->customer()->create();
    $client->assignRole(RoleName::Customer->value);
    $project = Project::factory()->inProgress()->create(['client_id' => $client->id]);

    $proposal = Proposal::factory()->accepted()->create([
        'project_id' => $project->id,
        'freelancer_id' => $profile->id,
        'proposed_amount' => Money::fromDecimal($total, 'CAD'),
    ]);

    $contract = Contract::factory()->active()->create([
        'project_id' => $project->id,
        'proposal_id' => $proposal->id,
        'total_amount' => Money::fromDecimal($total, 'CAD'),
    ]);

    return [$contract, $profile];
}

it('summarizes the freelancer dashboard', function () {
    [$user, $profile] = freelancerDashboardOwner();
    Proposal::factory()->submitted()->create(['freelancer_id' => $profile->id]);
    [$contract] = hiredContractFor($user, $profile);
    Milestone::factory()->submitted()->create(['contract_id' => $contract->id]);

    $this->withHeaders(authHeader($user))
        ->getJson('/api/v1/freelancer/me/dashboard')
        ->assertOk()
        ->assertJsonPath('data.open_proposal_count', 1)
        ->assertJsonPath('data.active_contract_count', 1)
        ->assertJsonCount(1, 'data.attention_milestones')
        ->assertJsonPath('data.earnings.currency', 'CAD');
});

it('denies a customer from viewing the freelancer dashboard', function () {
    $user = User::factory()->customer()->create();
    $user->assignRole(RoleName::Customer->value);

    $this->withHeaders(authHeader($user))
        ->getJson('/api/v1/freelancer/me/dashboard')
        ->assertForbidden();
});

it('rejects an unauthenticated request to the freelancer dashboard', function () {
    $this->getJson('/api/v1/freelancer/me/dashboard')->assertUnauthorized();
});

it('lists contracts created from the freelancer\'s own hired proposals', function () {
    [$user, $profile] = freelancerDashboardOwner();
    hiredContractFor($user, $profile);

    $other = FreelancerProfile::factory()->approved()->create();
    $otherUser = User::find($other->user_id);
    hiredContractFor($otherUser, $other);

    $this->withHeaders(authHeader($user))
        ->getJson('/api/v1/freelancer/me/contracts')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('rejects an unauthenticated request to freelancer contracts', function () {
    $this->getJson('/api/v1/freelancer/me/contracts')->assertUnauthorized();
});

it('lists released escrow payments in the freelancer earnings ledger', function () {
    [$user, $profile] = freelancerDashboardOwner();
    [$contract] = hiredContractFor($user, $profile);
    $milestone = Milestone::factory()->paid()->create(['contract_id' => $contract->id, 'amount' => Money::fromDecimal('300.00', 'CAD')]);

    Payment::factory()->escrow()->succeeded()->create([
        'payable_id' => $milestone->id,
        'amount' => Money::fromDecimal('300.00', 'CAD'),
        'stripe_transfer_id' => 'tr_released',
    ]);

    // A still-escrowed (not yet released) payment on another milestone still
    // shows up in the ledger, just flagged as not yet released.
    $pendingMilestone = Milestone::factory()->approved()->create(['contract_id' => $contract->id]);
    Payment::factory()->escrow()->succeeded()->create([
        'payable_id' => $pendingMilestone->id,
        'stripe_transfer_id' => null,
    ]);

    $response = $this->withHeaders(authHeader($user))
        ->getJson('/api/v1/freelancer/me/earnings')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $released = collect($response->json('data'))->pluck('released');
    expect($released)->toContain(true)->toContain(false);
});

it('rejects an unauthenticated request to freelancer earnings', function () {
    $this->getJson('/api/v1/freelancer/me/earnings')->assertUnauthorized();
});
