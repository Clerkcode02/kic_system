<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Models\Service;
use App\Domain\Freelance\Enums\MilestoneStatus;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\Payment\Actions\ReleaseMilestoneEscrow;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Services\PaymentGateway;
use App\Domain\Payment\Services\PaymentIntentResult;
use App\Domain\Payment\Services\RefundResult;
use App\Domain\Payment\Services\TransferResult;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use App\Support\ValueObjects\Money;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

final class StubTransferGateway implements PaymentGateway
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
 * @return array{0: User, 1: Business}
 */
function auditProviderOwner(): array
{
    $user = User::factory()->provider()->create();
    $user->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->verified()->create(['user_id' => $user->id]);

    return [$user, $business];
}

// --- Booking flow ---------------------------------------------------------

it('writes an audit entry when a booking is created and when its status changes', function () {
    $customer = User::factory()->customer()->create();
    $customer->assignRole(RoleName::Customer->value);
    [, $business] = auditProviderOwner();
    $service = Service::factory()->create(['business_id' => $business->id]);

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'status' => BookingStatus::WaitingForQuotation,
    ]);

    \App\Domain\Booking\Events\BookingCreated::dispatch($booking);

    expect(AuditLog::query()->where('action', 'booking.created')->where('auditable_id', $booking->id)->exists())->toBeTrue();

    app(\App\Domain\Booking\Actions\TransitionBookingStatus::class)->handle(
        $booking,
        BookingStatus::QuotationSent,
        \App\Support\ValueObjects\BookingActor::user($customer),
        'test transition',
    );

    $entry = AuditLog::query()
        ->where('action', 'booking.status_changed')
        ->where('auditable_type', 'booking')
        ->where('auditable_id', $booking->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->actor_id)->toBe($customer->id);
    expect($entry->before_state)->toBe(['status' => 'waiting_for_quotation']);
    expect($entry->after_state)->toBe(['status' => 'quotation_sent']);
});

// --- Milestone / escrow flow ------------------------------------------------

it('writes audit entries across milestone approval and escrow release', function () {
    $client = User::factory()->customer()->create();
    $client->assignRole(RoleName::Customer->value);

    $freelancerUser = User::factory()->freelancer()->create();
    $freelancerUser->assignRole(RoleName::Freelancer->value);
    $freelancer = FreelancerProfile::factory()->approved()->payoutsEnabled()->create(['user_id' => $freelancerUser->id]);

    $project = Project::factory()->inProgress()->create(['client_id' => $client->id]);
    $proposal = Proposal::factory()->accepted()->create(['project_id' => $project->id, 'freelancer_id' => $freelancer->id]);
    $contract = Contract::factory()->active()->create(['project_id' => $project->id, 'proposal_id' => $proposal->id]);
    $milestone = Milestone::factory()->submitted()->create(['contract_id' => $contract->id, 'amount' => Money::fromDecimal('300.00', 'CAD')]);

    Payment::factory()->succeeded()->escrow()->create([
        'payable_id' => $milestone->id,
        'amount' => $milestone->amount,
        'platform_fee_amount' => Money::fromDecimal('0.00', 'CAD'),
        'provider_net_amount' => $milestone->amount,
    ]);

    $milestone = app(\App\Domain\Freelance\Actions\TransitionMilestoneStatus::class)
        ->handle($milestone, MilestoneStatus::Approved, $client, 'Client approved the milestone.');

    expect(AuditLog::query()->where('action', 'milestone.status_changed')->where('auditable_id', $milestone->id)->where('after_state->status', 'approved')->exists())->toBeTrue();

    $gateway = new StubTransferGateway();
    $action = new ReleaseMilestoneEscrow($gateway, app(\App\Domain\Freelance\Actions\CompleteContract::class));
    $action->handle($milestone, $client);

    // Two distinct facts, both audited: the escrow release itself (manual
    // log in ReleaseMilestoneEscrow — carries transfer detail no event
    // exposes) and the resulting Paid status transition (via the global
    // Auditable listener).
    expect(AuditLog::query()->where('action', 'milestone.escrow_released')->where('auditable_id', $milestone->id)->exists())->toBeTrue();
    expect(AuditLog::query()->where('action', 'milestone.status_changed')->where('auditable_id', $milestone->id)->where('after_state->status', 'paid')->exists())->toBeTrue();
    expect(AuditLog::query()->where('action', 'contract.completed')->where('auditable_id', $contract->id)->exists())->toBeTrue();
});

// --- GET /audit-logs scoping -----------------------------------------------

it('scopes GET /api/v1/audit-logs: admin sees everything, provider sees only their own scope, customer is denied', function () {
    $customer = User::factory()->customer()->create();
    $customer->assignRole(RoleName::Customer->value);
    [$providerUser, $business] = auditProviderOwner();
    [$otherProviderUser, $otherBusiness] = auditProviderOwner();
    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);

    $service = Service::factory()->create(['business_id' => $business->id]);
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
    ]);

    $ownEntry = AuditLog::factory()->create([
        'actor_id' => $providerUser->id,
        'auditable_type' => 'booking',
        'auditable_id' => $booking->id,
    ]);

    $otherService = Service::factory()->create(['business_id' => $otherBusiness->id]);
    $otherBooking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $otherBusiness->id,
        'service_id' => $otherService->id,
    ]);

    $unrelatedEntry = AuditLog::factory()->create([
        'actor_id' => $otherProviderUser->id,
        'auditable_type' => 'booking',
        'auditable_id' => $otherBooking->id,
    ]);

    forgetAuthGuards();
    $adminToken = $admin->createToken('device')->plainTextToken;
    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/api/v1/audit-logs')
        ->assertOk()
        ->assertJsonFragment(['id' => $ownEntry->id])
        ->assertJsonFragment(['id' => $unrelatedEntry->id]);

    forgetAuthGuards();
    $providerToken = $providerUser->createToken('device')->plainTextToken;
    $response = $this->withHeader('Authorization', "Bearer {$providerToken}")
        ->getJson('/api/v1/audit-logs')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($ownEntry->id);
    expect($ids)->not->toContain($unrelatedEntry->id);

    forgetAuthGuards();
    $customerToken = $customer->createToken('device')->plainTextToken;
    $this->withHeader('Authorization', "Bearer {$customerToken}")
        ->getJson('/api/v1/audit-logs')
        ->assertForbidden();
});
