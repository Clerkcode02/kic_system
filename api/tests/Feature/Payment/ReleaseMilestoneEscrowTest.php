<?php

declare(strict_types=1);

use App\Domain\Freelance\Actions\CompleteContract;
use App\Domain\Freelance\Enums\ContractStatus;
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
use App\Domain\User\Models\User;
use App\Support\ValueObjects\Money;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

/**
 * Records every createTransfer() call instead of hitting Stripe, so the
 * test can assert "exactly one Transfer" directly regardless of how many
 * times the Action itself is invoked (simulating a retried job).
 */
final class CountingTransferGateway implements PaymentGateway
{
    public int $transferCalls = 0;

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
        $this->transferCalls++;

        return new TransferResult('tr_counted_'.$this->transferCalls);
    }

    public function refund(string $stripePaymentIntentId, Money $amount, string $idempotencyKey): RefundResult
    {
        return new RefundResult('re_fake');
    }
}

/**
 * @return array{0: Milestone, 1: Payment}
 */
function fundedApprovedMilestone(): array
{
    $client = User::factory()->customer()->create();
    $client->assignRole(\App\Domain\User\Enums\RoleName::Customer->value);

    $freelancerUser = User::factory()->freelancer()->create();
    $freelancerUser->assignRole(\App\Domain\User\Enums\RoleName::Freelancer->value);
    $freelancer = FreelancerProfile::factory()->approved()->payoutsEnabled()->create(['user_id' => $freelancerUser->id]);

    $project = Project::factory()->inProgress()->create(['client_id' => $client->id]);
    $proposal = Proposal::factory()->accepted()->create(['project_id' => $project->id, 'freelancer_id' => $freelancer->id]);
    $contract = Contract::factory()->active()->create(['project_id' => $project->id, 'proposal_id' => $proposal->id]);
    $milestone = Milestone::factory()->approved()->create(['contract_id' => $contract->id, 'amount' => Money::fromDecimal('300.00', 'CAD')]);

    $payment = Payment::factory()->succeeded()->escrow()->create([
        'payable_id' => $milestone->id,
        'amount' => $milestone->amount,
        'platform_fee_amount' => Money::fromDecimal('0.00', 'CAD'),
        'provider_net_amount' => $milestone->amount,
    ]);

    return [$milestone, $payment];
}

it('releases escrow exactly once even if the action runs twice (simulated job retry)', function () {
    [$milestone] = fundedApprovedMilestone();

    $gateway = new CountingTransferGateway();
    $this->app->instance(PaymentGateway::class, $gateway);

    $action = new ReleaseMilestoneEscrow($gateway, app(CompleteContract::class));

    $action->handle($milestone);
    $action->handle($milestone->fresh());

    expect($gateway->transferCalls)->toBe(1);
    expect($milestone->fresh()->status)->toBe(MilestoneStatus::Paid);
});

it('applies the platform fee and completes the contract on release', function () {
    [$milestone, $payment] = fundedApprovedMilestone();

    $gateway = new CountingTransferGateway();
    $action = new ReleaseMilestoneEscrow($gateway, app(CompleteContract::class));

    $action->handle($milestone);

    // platform.default_fee_percentage seeded at 10%.
    expect($payment->fresh()->platform_fee_amount->toDecimal())->toBe('30.00');
    expect($payment->fresh()->provider_net_amount->toDecimal())->toBe('270.00');
    expect($payment->fresh()->stripe_transfer_id)->not->toBeNull();
    expect($milestone->contract->fresh()->status)->toBe(ContractStatus::Completed);
});

it('refuses to release escrow for a milestone that has not been approved', function () {
    [$milestone] = fundedApprovedMilestone();
    $milestone->update(['status' => MilestoneStatus::Submitted]);

    $gateway = new CountingTransferGateway();
    $action = new ReleaseMilestoneEscrow($gateway, app(CompleteContract::class));

    expect(fn () => $action->handle($milestone->fresh()))->toThrow(\App\Support\ConflictException::class);
    expect($gateway->transferCalls)->toBe(0);
});
