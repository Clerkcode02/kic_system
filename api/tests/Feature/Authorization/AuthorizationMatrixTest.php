<?php

declare(strict_types=1);

use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Models\Service;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Freelance\Enums\FreelancerApprovalStatus;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\Payment\Models\Payment;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\Review\Models\Review;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

/**
 * SRS §6 — a customer account with the `customer` spatie role.
 */
function customerUser(): User
{
    $user = User::factory()->customer()->create();
    $user->assignRole(RoleName::Customer->value);

    return $user;
}

/**
 * @return array{0: User, 1: Business}
 */
function providerOwner(): array
{
    $user = User::factory()->provider()->create();
    $user->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->verified()->create(['user_id' => $user->id]);

    return [$user, $business];
}

/**
 * @return array{0: User, 1: FreelancerProfile}
 */
function freelancerUser(bool $approved = true): array
{
    $user = User::factory()->freelancer()->create();
    $user->assignRole(RoleName::Freelancer->value);
    $profile = FreelancerProfile::factory()->create([
        'user_id' => $user->id,
        'approval_status' => $approved ? FreelancerApprovalStatus::Approved : FreelancerApprovalStatus::Pending,
    ]);

    return [$user, $profile];
}

function adminUser(): User
{
    $user = User::factory()->admin()->create();
    $user->assignRole(RoleName::Admin->value);

    return $user;
}

// --- Bookings ---------------------------------------------------------

it('lets only the owning customer request a booking', function () {
    $customer = customerUser();
    [$provider] = providerOwner();
    [$freelancer] = freelancerUser();
    $admin = adminUser();

    expect($customer->can('create', Booking::class))->toBeTrue();
    expect($provider->can('create', Booking::class))->toBeFalse();
    expect($freelancer->can('create', Booking::class))->toBeFalse();
    expect($admin->can('create', Booking::class))->toBeFalse();
});

it('scopes booking view/cancel to the customer and the owning provider only', function () {
    $customer = customerUser();
    $otherCustomer = customerUser();
    [$provider, $business] = providerOwner();
    [$otherProvider] = providerOwner();
    $admin = adminUser();

    $service = Service::factory()->create(['business_id' => $business->id]);
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
    ]);

    expect($customer->can('view', $booking))->toBeTrue();
    expect($provider->can('view', $booking))->toBeTrue();
    expect($otherCustomer->can('view', $booking))->toBeFalse();
    expect($otherProvider->can('view', $booking))->toBeFalse();
    expect($admin->can('view', $booking))->toBeTrue();

    expect($customer->can('cancel', $booking))->toBeTrue();
    expect($provider->can('cancel', $booking))->toBeTrue();
    expect($otherCustomer->can('cancel', $booking))->toBeFalse();
});

// --- Quotations ---------------------------------------------------------

it('only lets the owning verified provider send or revise a quotation', function () {
    $customer = customerUser();
    [$provider, $business] = providerOwner();
    [$otherProvider] = providerOwner();

    $service = Service::factory()->create(['business_id' => $business->id]);
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
    ]);
    $quotation = Quotation::factory()->create(['booking_id' => $booking->id]);

    expect($provider->can('create', [Quotation::class, $booking]))->toBeTrue();
    expect($otherProvider->can('create', [Quotation::class, $booking]))->toBeFalse();
    expect($customer->can('create', [Quotation::class, $booking]))->toBeFalse();

    expect($provider->can('revise', $quotation))->toBeTrue();
    expect($otherProvider->can('revise', $quotation))->toBeFalse();
    expect($customer->can('revise', $quotation))->toBeFalse();
});

it('only lets the owning customer accept or reject a quotation', function () {
    $customer = customerUser();
    $otherCustomer = customerUser();
    [$provider, $business] = providerOwner();

    $service = Service::factory()->create(['business_id' => $business->id]);
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
    ]);
    $quotation = Quotation::factory()->create(['booking_id' => $booking->id]);

    expect($customer->can('accept', $quotation))->toBeTrue();
    expect($customer->can('reject', $quotation))->toBeTrue();
    expect($otherCustomer->can('accept', $quotation))->toBeFalse();
    expect($provider->can('accept', $quotation))->toBeFalse();
});

// --- Freelance: projects & proposals ------------------------------------

it('lets only a customer (as client) publish a project', function () {
    $customer = customerUser();
    [$provider] = providerOwner();
    [$freelancer] = freelancerUser();
    $admin = adminUser();

    expect($customer->can('create', Project::class))->toBeTrue();
    expect($provider->can('create', Project::class))->toBeFalse();
    expect($freelancer->can('create', Project::class))->toBeFalse();
    expect($admin->can('create', Project::class))->toBeFalse();
});

it('everyone can browse projects, but only the owning client can update one', function () {
    $client = customerUser();
    $otherCustomer = customerUser();
    [$provider] = providerOwner();
    [$freelancer] = freelancerUser();
    $admin = adminUser();

    $project = Project::factory()->create(['client_id' => $client->id]);

    foreach ([$client, $otherCustomer, $provider, $freelancer, $admin] as $viewer) {
        expect($viewer->can('view', $project))->toBeTrue();
    }

    expect($client->can('update', $project))->toBeTrue();
    expect($otherCustomer->can('update', $project))->toBeFalse();
    expect($admin->can('update', $project))->toBeFalse();
});

it('only lets an approved freelancer submit a proposal', function () {
    $client = customerUser();
    [$approvedFreelancer] = freelancerUser(approved: true);
    [$pendingFreelancer] = freelancerUser(approved: false);
    [$provider] = providerOwner();

    $project = Project::factory()->create(['client_id' => $client->id]);

    expect($approvedFreelancer->can('create', [Proposal::class, $project]))->toBeTrue();
    expect($pendingFreelancer->can('create', [Proposal::class, $project]))->toBeFalse();
    expect($provider->can('create', [Proposal::class, $project]))->toBeFalse();
    expect($client->can('create', [Proposal::class, $project]))->toBeFalse();
});

it('only lets the project client hire a proposal', function () {
    $client = customerUser();
    $otherCustomer = customerUser();
    [, $profile] = freelancerUser();

    $project = Project::factory()->create(['client_id' => $client->id]);
    $proposal = Proposal::factory()->create(['project_id' => $project->id, 'freelancer_id' => $profile->id]);

    expect($client->can('hire', $proposal))->toBeTrue();
    expect($otherCustomer->can('hire', $proposal))->toBeFalse();
});

// --- Milestones: approval is exclusively the client's -------------------

it('lets the client approve a milestone and the freelancer submit it, and no one else', function () {
    $client = customerUser();
    [$freelancer, $profile] = freelancerUser();
    [$otherFreelancer] = freelancerUser();
    [$provider] = providerOwner();
    $admin = adminUser();

    $project = Project::factory()->create(['client_id' => $client->id]);
    $proposal = Proposal::factory()->create(['project_id' => $project->id, 'freelancer_id' => $profile->id]);
    $contract = Contract::factory()->create(['project_id' => $project->id, 'proposal_id' => $proposal->id]);
    $milestone = Milestone::factory()->create(['contract_id' => $contract->id]);

    expect($client->can('approve', $milestone))->toBeTrue();
    expect($freelancer->can('approve', $milestone))->toBeFalse();
    expect($admin->can('approve', $milestone))->toBeFalse();
    expect($provider->can('approve', $milestone))->toBeFalse();

    expect($freelancer->can('submit', $milestone))->toBeTrue();
    expect($otherFreelancer->can('submit', $milestone))->toBeFalse();
    expect($client->can('submit', $milestone))->toBeFalse();
});

// --- Businesses & freelancers: admin-only approval -----------------------

it('lets only admins approve or reject a business, and only the owner manage it', function () {
    [$owner, $business] = providerOwner();
    [$otherProvider] = providerOwner();
    $customer = customerUser();
    $admin = adminUser();

    expect($admin->can('approve', $business))->toBeTrue();
    expect($owner->can('approve', $business))->toBeFalse();
    expect($customer->can('approve', $business))->toBeFalse();

    expect($owner->can('manage', $business))->toBeTrue();
    expect($otherProvider->can('manage', $business))->toBeFalse();
    expect($admin->can('manage', $business))->toBeFalse();
});

// --- Services -------------------------------------------------------------

it('lets only the owning business manage its services', function () {
    [$owner, $business] = providerOwner();
    [$otherProvider] = providerOwner();
    $customer = customerUser();

    $service = Service::factory()->create(['business_id' => $business->id]);

    expect($owner->can('manage', $service))->toBeTrue();
    expect($otherProvider->can('manage', $service))->toBeFalse();
    expect($customer->can('manage', $service))->toBeFalse();
});

// --- Reviews --------------------------------------------------------------

it('lets only the customer leave a review, and only the reviewee reply', function () {
    $customer = customerUser();
    [$provider, $business] = providerOwner();
    [$freelancer] = freelancerUser();
    $admin = adminUser();

    expect($customer->can('create', Review::class))->toBeTrue();
    expect($provider->can('create', Review::class))->toBeFalse();
    expect($freelancer->can('create', Review::class))->toBeFalse();
    expect($admin->can('create', Review::class))->toBeFalse();

    $service = Service::factory()->create(['business_id' => $business->id]);
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
    ]);
    $review = Review::factory()->create([
        'reviewer_id' => $customer->id,
        'reviewee_id' => $provider->id,
        'reviewable_type' => 'booking',
        'reviewable_id' => $booking->id,
    ]);

    expect($provider->can('reply', $review))->toBeTrue();
    expect($customer->can('reply', $review))->toBeFalse();
});

// --- Disputes ---------------------------------------------------------

it('lets transaction participants raise disputes but only admins resolve them', function () {
    $customer = customerUser();
    [$provider] = providerOwner();
    [$freelancer] = freelancerUser();
    $admin = adminUser();

    expect($customer->can('create', Dispute::class))->toBeTrue();
    expect($provider->can('create', Dispute::class))->toBeTrue();
    expect($freelancer->can('create', Dispute::class))->toBeTrue();

    $dispute = Dispute::factory()->create(['raised_by' => $customer->id]);

    expect($admin->can('resolve', $dispute))->toBeTrue();
    expect($customer->can('resolve', $dispute))->toBeFalse();
    expect($provider->can('resolve', $dispute))->toBeFalse();
});

// --- Payments -----------------------------------------------------------

it('lets only the customer pay for their booking, and only admins issue refunds', function () {
    $customer = customerUser();
    [$provider, $business] = providerOwner();

    $service = Service::factory()->create(['business_id' => $business->id]);
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
    ]);

    expect($customer->can('create', [Payment::class, $booking]))->toBeTrue();
    expect($provider->can('create', [Payment::class, $booking]))->toBeFalse();

    $payment = Payment::factory()->create(['payable_type' => 'booking', 'payable_id' => $booking->id]);
    $admin = adminUser();

    expect($admin->can('refund', $payment))->toBeTrue();
    expect($customer->can('refund', $payment))->toBeFalse();
});

// --- Categories & platform fees: admin-only, permission is data ---------

it('grants category and platform-fee management only to admins', function () {
    $customer = customerUser();
    [$provider] = providerOwner();
    [$freelancer] = freelancerUser();
    $admin = adminUser();

    foreach ([$customer, $provider, $freelancer] as $nonAdmin) {
        expect($nonAdmin->can('categories.manage'))->toBeFalse();
        expect($nonAdmin->can('platform-settings.manage'))->toBeFalse();
    }

    expect($admin->can('categories.manage'))->toBeTrue();
    expect($admin->can('platform-settings.manage'))->toBeTrue();
});

// --- Audit trail: admin full, provider/freelancer own-scope only, customer none ---

it('scopes the audit trail per SRS §6: admin full, provider/freelancer own scope, customer none', function () {
    $customer = customerUser();
    [$provider, $business] = providerOwner();
    [$otherProvider] = providerOwner();
    [$freelancer] = freelancerUser();
    $admin = adminUser();

    $service = Service::factory()->create(['business_id' => $business->id]);
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
    ]);

    // Entry where the provider is the actor.
    $actorEntry = \App\Domain\Audit\Models\AuditLog::factory()->create([
        'actor_id' => $provider->id,
        'auditable_type' => 'booking',
        'auditable_id' => $booking->id,
    ]);

    // Entry where an unrelated admin acted, but the subject (this booking)
    // still belongs to the provider — "subject entity belongs to them".
    $subjectEntry = \App\Domain\Audit\Models\AuditLog::factory()->create([
        'actor_id' => $admin->id,
        'auditable_type' => 'booking',
        'auditable_id' => $booking->id,
    ]);

    expect($admin->can('view', $actorEntry))->toBeTrue();
    expect($admin->can('view', $subjectEntry))->toBeTrue();

    expect($provider->can('view', $actorEntry))->toBeTrue();
    expect($provider->can('view', $subjectEntry))->toBeTrue();
    expect($otherProvider->can('view', $subjectEntry))->toBeFalse();

    expect($freelancer->can('view', $subjectEntry))->toBeFalse();
    expect($customer->can('view', $actorEntry))->toBeFalse();
    expect($customer->can('view', $subjectEntry))->toBeFalse();
});

// --- Suspension: applies at the auth layer, before any policy runs -----

it('returns 403 with a machine-readable code on every protected route once suspended', function () {
    $user = User::factory()->customer()->create();

    forgetAuthGuards();
    $token = $user->createToken('device')->plainTextToken;

    $user->forceFill(['status' => \App\Domain\User\Enums\UserStatus::Suspended])->save();

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'account_suspended');

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertForbidden()
        ->assertJsonPath('code', 'account_suspended');

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout-all-devices')
        ->assertForbidden()
        ->assertJsonPath('code', 'account_suspended');
});
