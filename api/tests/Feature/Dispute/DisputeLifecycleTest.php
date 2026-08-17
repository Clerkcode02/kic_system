<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Dispute\Enums\DisputeStatus;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Freelance\Enums\MilestoneStatus;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\Payment\Actions\ReleaseMilestoneEscrow;
use App\Domain\Payment\Jobs\ReleaseMilestoneEscrowJob;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Services\PaymentGateway;
use App\Domain\Payment\Services\PaymentIntentResult;
use App\Domain\Payment\Services\RefundResult;
use App\Domain\Payment\Services\TransferResult;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use App\Support\ConflictException;
use App\Support\ValueObjects\Money;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

final class DisputeStubGateway implements PaymentGateway
{
    public function createBookingPaymentIntent(Money $amount, Money $applicationFeeAmount, string $connectedAccountId, array $metadata = []): PaymentIntentResult
    {
        return new PaymentIntentResult('pi_fake', 'secret');
    }

    public function createMilestonePaymentIntent(Money $amount, array $metadata = []): PaymentIntentResult
    {
        return new PaymentIntentResult('pi_fake', 'secret');
    }

    public function createTransfer(Money $amount, string $connectedAccountId, string $idempotencyKey, array $metadata = []): TransferResult
    {
        return new TransferResult('tr_fake');
    }

    public function refund(string $stripePaymentIntentId, Money $amount, string $idempotencyKey): RefundResult
    {
        return new RefundResult('re_fake');
    }
}

/**
 * @return array{0: Milestone, 1: User, 2: User}
 */
function disputedFundedMilestone(): array
{
    $client = User::factory()->customer()->create();
    $client->assignRole(RoleName::Customer->value);

    $freelancerUser = User::factory()->freelancer()->create();
    $freelancerUser->assignRole(RoleName::Freelancer->value);
    $freelancer = FreelancerProfile::factory()->approved()->payoutsEnabled()->create(['user_id' => $freelancerUser->id]);

    $project = Project::factory()->inProgress()->create(['client_id' => $client->id]);
    $proposal = Proposal::factory()->accepted()->create(['project_id' => $project->id, 'freelancer_id' => $freelancer->id]);
    $contract = Contract::factory()->active()->create(['project_id' => $project->id, 'proposal_id' => $proposal->id]);
    $milestone = Milestone::factory()->approved()->create(['contract_id' => $contract->id, 'amount' => Money::fromDecimal('300.00', 'CAD')]);

    Payment::factory()->succeeded()->escrow()->create([
        'payable_id' => $milestone->id,
        'amount' => $milestone->amount,
        'platform_fee_amount' => Money::fromDecimal('0.00', 'CAD'),
        'provider_net_amount' => $milestone->amount,
    ]);

    return [$milestone, $client, $freelancerUser];
}

it('lets a party to a milestone raise a dispute, and denies a stranger', function () {
    [$milestone, $client] = disputedFundedMilestone();
    $stranger = User::factory()->customer()->create();
    $stranger->assignRole(RoleName::Customer->value);

    forgetAuthGuards();
    $clientToken = $client->createToken('device')->plainTextToken;
    $this->withHeader('Authorization', "Bearer {$clientToken}")
        ->postJson('/api/v1/disputes', [
            'disputable_type' => 'milestone',
            'disputable_id' => $milestone->id,
            'reason' => 'The delivered work does not match the agreed scope.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', DisputeStatus::Open->value);

    expect(Dispute::query()->where('disputable_type', 'milestone')->where('disputable_id', $milestone->id)->exists())->toBeTrue();

    forgetAuthGuards();
    $strangerToken = $stranger->createToken('device')->plainTextToken;
    $this->withHeader('Authorization', "Bearer {$strangerToken}")
        ->postJson('/api/v1/disputes', [
            'disputable_type' => 'milestone',
            'disputable_id' => $milestone->id,
            'reason' => 'Unrelated stranger trying to dispute someone else\'s milestone.',
        ])
        ->assertStatus(409);
});

it('rejects raising a dispute against an entity that does not exist', function () {
    $client = User::factory()->customer()->create();
    $client->assignRole(RoleName::Customer->value);
    $token = $client->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/disputes', [
            'disputable_type' => 'milestone',
            'disputable_id' => (string) Str::uuid7(),
            'reason' => 'This milestone does not exist at all.',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('disputable_id');
});

it('rejects raising a second dispute while one is already open on the same entity', function () {
    [$milestone, $client] = disputedFundedMilestone();

    Dispute::create([
        'disputable_type' => 'milestone',
        'disputable_id' => $milestone->id,
        'raised_by' => $client->id,
        'status' => DisputeStatus::Open,
        'resolution_notes' => 'First dispute on this milestone.',
    ]);

    $token = $client->createToken('device')->plainTextToken;
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/disputes', [
            'disputable_type' => 'milestone',
            'disputable_id' => $milestone->id,
            'reason' => 'Trying to raise a second dispute on the same milestone.',
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'dispute_already_open');

    expect(Dispute::query()->where('disputable_type', 'milestone')->where('disputable_id', $milestone->id)->count())->toBe(1);
});

it('lets either party to a booking raise a dispute on it, and denies a stranger', function () {
    [$customer, $address] = bookingCustomer();
    [$providerUser, $business] = bookingProvider();
    $service = bookingService($business, ServicePricingType::Fixed);

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'status' => BookingStatus::Completed,
    ]);

    $providerToken = $providerUser->createToken('device')->plainTextToken;
    $this->withHeader('Authorization', "Bearer {$providerToken}")
        ->postJson('/api/v1/disputes', [
            'disputable_type' => 'booking',
            'disputable_id' => $booking->id,
            'reason' => 'The customer never paid the remainder owed.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', DisputeStatus::Open->value);

    $stranger = User::factory()->customer()->create();
    $stranger->assignRole(RoleName::Customer->value);
    $strangerToken = $stranger->createToken('device')->plainTextToken;

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$strangerToken}")
        ->postJson('/api/v1/disputes', [
            'disputable_type' => 'booking',
            'disputable_id' => $booking->id,
            'reason' => 'An unrelated stranger raising a dispute on this booking.',
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'not_a_party_to_disputable');
});

it('lets a client raise a dispute on their own project, and denies a stranger', function () {
    $client = User::factory()->customer()->create();
    $client->assignRole(RoleName::Customer->value);

    $project = Project::factory()->inProgress()->create(['client_id' => $client->id]);

    $token = $client->createToken('device')->plainTextToken;
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/disputes', [
            'disputable_type' => 'project',
            'disputable_id' => $project->id,
            'reason' => 'This project engagement has gone sideways.',
        ])
        ->assertCreated();

    $stranger = User::factory()->customer()->create();
    $stranger->assignRole(RoleName::Customer->value);
    $strangerToken = $stranger->createToken('device')->plainTextToken;

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$strangerToken}")
        ->postJson('/api/v1/disputes', [
            'disputable_type' => 'project',
            'disputable_id' => $project->id,
            'reason' => 'An unrelated stranger raising a dispute on this project.',
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'not_a_party_to_disputable');
});

it('freezes escrow release while a milestone dispute is open, and releases it once resolved', function () {
    [$milestone, $client, $freelancerUser] = disputedFundedMilestone();

    $dispute = Dispute::create([
        'disputable_type' => 'milestone',
        'disputable_id' => $milestone->id,
        'raised_by' => $client->id,
        'status' => DisputeStatus::Open,
        'resolution_notes' => 'Work quality dispute.',
    ]);

    $gateway = new DisputeStubGateway();
    $action = new ReleaseMilestoneEscrow($gateway, app(\App\Domain\Freelance\Actions\CompleteContract::class));

    expect(fn () => $action->handle($milestone->fresh(), $client))->toThrow(ConflictException::class);
    expect($milestone->fresh()->status)->toBe(MilestoneStatus::Approved);

    Queue::fake();

    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);

    app(\App\Domain\Dispute\Actions\ResolveDispute::class)->handle(
        $dispute,
        $admin,
        'Reviewed the deliverable — it satisfies the agreed scope. Releasing escrow.',
        true,
    );

    expect($dispute->fresh()->status)->toBe(DisputeStatus::Resolved);
    Queue::assertPushed(ReleaseMilestoneEscrowJob::class, fn (ReleaseMilestoneEscrowJob $job) => $job->milestoneId === $milestone->id);

    // Now that the dispute is resolved, a direct release call succeeds.
    $action->handle($milestone->fresh(), $admin);
    expect($milestone->fresh()->status)->toBe(MilestoneStatus::Paid);
});

it('denies a non-admin from resolving a dispute', function () {
    [$milestone, $client] = disputedFundedMilestone();

    $dispute = Dispute::create([
        'disputable_type' => 'milestone',
        'disputable_id' => $milestone->id,
        'raised_by' => $client->id,
        'status' => DisputeStatus::Open,
        'resolution_notes' => 'Work quality dispute.',
    ]);

    forgetAuthGuards();
    $clientToken = $client->createToken('device')->plainTextToken;
    $this->withHeader('Authorization', "Bearer {$clientToken}")
        ->postJson("/api/v1/disputes/{$dispute->id}/resolve", [
            'resolution_notes' => 'I am trying to resolve my own dispute.',
        ])
        ->assertForbidden();
});
