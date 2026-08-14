<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\Payment\Models\Payment;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

/**
 * @return array{0: User, 1: FreelancerProfile, 2: Milestone}
 */
function fundableMilestone(bool $freelancerPayoutsEnabled = true): array
{
    $client = User::factory()->customer()->create();
    $client->assignRole(RoleName::Customer->value);

    $freelancerUser = User::factory()->freelancer()->create();
    $freelancerUser->assignRole(RoleName::Freelancer->value);
    $freelancerProfileFactory = FreelancerProfile::factory()->approved();
    $freelancerProfile = $freelancerPayoutsEnabled
        ? $freelancerProfileFactory->payoutsEnabled()->create(['user_id' => $freelancerUser->id])
        : $freelancerProfileFactory->create(['user_id' => $freelancerUser->id]);

    $project = Project::factory()->inProgress()->create(['client_id' => $client->id]);
    $proposal = Proposal::factory()->accepted()->create([
        'project_id' => $project->id,
        'freelancer_id' => $freelancerProfile->id,
    ]);
    $contract = Contract::factory()->active()->create([
        'project_id' => $project->id,
        'proposal_id' => $proposal->id,
    ]);
    $milestone = Milestone::factory()->pending()->create(['contract_id' => $contract->id]);

    return [$client, $freelancerProfile, $milestone];
}

// --- Milestone escrow funding ------------------------------------------------

it('funds a milestone into escrow with the server-recomputed amount, ignoring any client-sent amount', function () {
    [$client, , $milestone] = fundableMilestone();

    $response = $this->withHeaders(authHeader($client))
        ->postJson('/api/v1/payments/intents', [
            'payable_type' => 'milestone',
            'payable_id' => $milestone->id,
            // Forged field a malicious client might send — the FormRequest
            // doesn't define a rule for it and the Action never reads it.
            'amount' => '1.00',
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();

    $response
        ->assertJsonPath('data.amount', $milestone->amount->toDecimal())
        ->assertJsonPath('data.type', 'escrow')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('meta.client_secret', fn ($value) => is_string($value));

    $payment = Payment::query()->where('payable_type', 'milestone')->where('payable_id', $milestone->id)->firstOrFail();
    expect($payment->amount->toDecimal())->toBe($milestone->amount->toDecimal());
    expect($payment->amount->toDecimal())->not->toBe('1.00');
});

it('replays an idempotency key for a milestone funding request without creating a second intent', function () {
    [$client, , $milestone] = fundableMilestone();
    $key = (string) Str::uuid();

    $first = $this->withHeaders(authHeader($client))
        ->postJson('/api/v1/payments/intents', [
            'payable_type' => 'milestone',
            'payable_id' => $milestone->id,
        ], ['Idempotency-Key' => $key])
        ->assertCreated();

    forgetAuthGuards();
    $second = $this->withHeaders(authHeader($client))
        ->postJson('/api/v1/payments/intents', [
            'payable_type' => 'milestone',
            'payable_id' => $milestone->id,
        ], ['Idempotency-Key' => $key])
        ->assertCreated();

    expect($second->json('data.stripe_payment_intent_id'))->toBe($first->json('data.stripe_payment_intent_id'));
    expect(Payment::query()->where('payable_type', 'milestone')->where('payable_id', $milestone->id)->count())->toBe(1);
});

it('blocks funding a milestone whose freelancer cannot receive payouts yet', function () {
    [$client, , $milestone] = fundableMilestone(freelancerPayoutsEnabled: false);

    $this->withHeaders(authHeader($client))
        ->postJson('/api/v1/payments/intents', [
            'payable_type' => 'milestone',
            'payable_id' => $milestone->id,
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(403)
        ->assertJsonPath('code', 'freelancer_payouts_not_enabled');

    expect(Payment::query()->where('payable_type', 'milestone')->where('payable_id', $milestone->id)->exists())->toBeFalse();
});

it('rejects a stranger from funding someone else\'s milestone', function () {
    [, , $milestone] = fundableMilestone();
    $stranger = User::factory()->customer()->create();
    $stranger->assignRole(RoleName::Customer->value);

    $this->withHeaders(authHeader($stranger))
        ->postJson('/api/v1/payments/intents', [
            'payable_type' => 'milestone',
            'payable_id' => $milestone->id,
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden();
});

// --- Booking deposit remainder via the generic intents endpoint --------------

it('funds the deposit at acceptance and the remainder via the intents endpoint at completion', function () {
    [$providerUser, $business] = bookingProvider();
    [$customer, $address] = bookingCustomer();
    $service = bookingService($business, ServicePricingType::Hourly);

    $booking = Booking::factory()->waitingForQuotation()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
    ]);

    $this->withHeaders(authHeader($providerUser))
        ->postJson("/api/v1/bookings/{$booking->id}/quotations", [
            'labor_cost' => '200.00',
            'materials_cost' => '0',
            'additional_fees' => '0',
        ])
        ->assertCreated();

    $quotation = Quotation::query()->firstOrFail();
    $quotation->update(['deposit_percentage' => 25.0]);

    forgetAuthGuards();
    $accept = $this->withHeaders(authHeader($customer))
        ->postJson("/api/v1/quotations/{$quotation->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertOk();

    $deposit = Payment::query()->where('payable_id', $booking->id)->where('type', 'deposit')->firstOrFail();
    expect($deposit->amount->toDecimal())->toBe(
        bcdiv(bcmul($quotation->total_amount->toDecimal(), '25', 2), '100', 2)
    );

    // Full balance isn't paid yet — the intents endpoint should compute
    // exactly the remainder, not the full quotation total again.
    forgetAuthGuards();
    $remainder = $this->withHeaders(authHeader($customer))
        ->postJson('/api/v1/payments/intents', [
            'payable_type' => 'booking',
            'payable_id' => $booking->id,
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();

    $remainder->assertJsonPath('data.type', 'partial');
    expect($remainder->json('data.amount'))->not->toBe($quotation->total_amount->toDecimal());
});

it('blocks a provider without payouts enabled from having a quotation sent on their behalf', function () {
    [$providerUser, $business] = bookingProvider();
    $business->update(['stripe_payouts_enabled' => false]);

    [$customer, $address] = bookingCustomer();
    $service = bookingService($business, ServicePricingType::Hourly);

    $booking = Booking::factory()->waitingForQuotation()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'status' => BookingStatus::WaitingForQuotation,
    ]);

    $this->withHeaders(authHeader($providerUser))
        ->postJson("/api/v1/bookings/{$booking->id}/quotations", [
            'labor_cost' => '100.00',
            'materials_cost' => '0',
            'additional_fees' => '0',
        ])
        ->assertStatus(403)
        ->assertJsonPath('code', 'provider_payouts_not_enabled');
});
