<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\StripeEvent;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\WebhookSignature;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    config(['services.stripe.webhook_secret' => 'whsec_test_123']);
});

/**
 * Builds a valid Stripe-Signature header for $payload (a PHP array), signed
 * exactly the way postJson() will encode it (plain json_encode, no extra
 * flags) so the controller's Webhook::constructEvent() verifies cleanly.
 *
 * @param  array<string, mixed>  $payload
 */
function postSignedStripeWebhook(array $payload): \Illuminate\Testing\TestResponse
{
    $encoded = json_encode($payload);
    $header = WebhookSignature::generateSignatureHeader($encoded, 'whsec_test_123');

    return test()->postJson('/api/v1/webhooks/stripe', $payload, ['Stripe-Signature' => $header]);
}

/**
 * @return array{0: User, 1: Booking, 2: Payment}
 */
function acceptedBookingWithPendingPayment(): array
{
    [$customer, $address] = bookingCustomer();
    [$providerUser, $business] = bookingProvider();
    $service = bookingService($business, ServicePricingType::Hourly);

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'provider_id' => $business->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'status' => BookingStatus::Accepted,
    ]);

    $quotation = Quotation::factory()->accepted()->create(['booking_id' => $booking->id]);

    $payment = Payment::factory()->pending()->forBooking($booking)->create([
        'stripe_payment_intent_id' => 'pi_webhook_test_1',
        'amount' => $quotation->total_amount,
    ]);

    return [$customer, $booking, $payment];
}

function paymentIntentSucceededPayload(string $eventId, string $intentId): array
{
    return [
        'id' => $eventId,
        'object' => 'event',
        'api_version' => '2024-01-01',
        'created' => time(),
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => $intentId, 'object' => 'payment_intent']],
    ];
}

it('rejects a webhook with an invalid signature', function () {
    $response = test()->postJson('/api/v1/webhooks/stripe', ['id' => 'evt_bad'], ['Stripe-Signature' => 't=1,v1=bogus']);

    $response->assertStatus(400);
});

it('replaying an identical webhook twice produces exactly one state change', function () {
    [, $booking, $payment] = acceptedBookingWithPendingPayment();

    $event = paymentIntentSucceededPayload('evt_replay_1', $payment->stripe_payment_intent_id);

    postSignedStripeWebhook($event)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
    expect($booking->fresh()->status)->toBe(BookingStatus::Scheduled);
    expect(StripeEvent::query()->count())->toBe(1);

    // Same event id delivered again (Stripe's own retry behaviour).
    forgetAuthGuards();
    postSignedStripeWebhook($event)->assertOk();

    expect(StripeEvent::query()->count())->toBe(1);
    expect($booking->fresh()->status)->toBe(BookingStatus::Scheduled);
    expect($booking->fresh()->statusHistory()->where('to_status', BookingStatus::Scheduled->value)->count())->toBe(1);
});

it('acknowledges and skips a duplicate event id without dispatching a second job', function () {
    [, , $payment] = acceptedBookingWithPendingPayment();

    $event = paymentIntentSucceededPayload('evt_dup_1', $payment->stripe_payment_intent_id);

    postSignedStripeWebhook($event)->assertOk()->assertJsonPath('received', true);

    forgetAuthGuards();
    postSignedStripeWebhook($event)->assertOk()->assertJsonPath('received', true);

    expect(StripeEvent::query()->where('stripe_event_id', 'evt_dup_1')->count())->toBe(1);
});

it('cannot schedule a booking without a succeeded payment row', function () {
    [, $booking] = acceptedBookingWithPendingPayment();

    $transition = app(\App\Domain\Booking\Actions\TransitionBookingStatus::class);

    expect(fn () => $transition->handle($booking, BookingStatus::Scheduled, null))
        ->toThrow(\App\Support\ConflictException::class);

    expect($booking->fresh()->status)->toBe(BookingStatus::Accepted);
});

it('reconciles a connected account status from account.updated', function () {
    $user = User::factory()->provider()->create();
    $business = Business::factory()->create([
        'user_id' => $user->id,
        'stripe_connect_account_id' => 'acct_webhook_1',
        'stripe_charges_enabled' => false,
        'stripe_payouts_enabled' => false,
    ]);

    $event = [
        'id' => 'evt_account_1',
        'object' => 'event',
        'api_version' => '2024-01-01',
        'created' => time(),
        'type' => 'account.updated',
        'data' => ['object' => [
            'id' => 'acct_webhook_1',
            'object' => 'account',
            'charges_enabled' => true,
            'payouts_enabled' => true,
        ]],
    ];

    postSignedStripeWebhook($event)->assertOk();

    expect($business->fresh()->stripe_charges_enabled)->toBeTrue();
    expect($business->fresh()->stripe_payouts_enabled)->toBeTrue();
});
