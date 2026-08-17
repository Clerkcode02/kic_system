<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Booking\Enums\BookingPaymentStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Dispute\Enums\DisputeStatus;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\RefundStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Refund;
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

function paymentIntentFailedPayload(string $eventId, string $intentId): array
{
    return [
        'id' => $eventId,
        'object' => 'event',
        'api_version' => '2024-01-01',
        'created' => time(),
        'type' => 'payment_intent.payment_failed',
        'data' => ['object' => ['id' => $intentId, 'object' => 'payment_intent']],
    ];
}

function chargeRefundedPayload(string $eventId, string $intentId): array
{
    return [
        'id' => $eventId,
        'object' => 'event',
        'api_version' => '2024-01-01',
        'created' => time(),
        'type' => 'charge.refunded',
        'data' => ['object' => ['id' => 'ch_'.$eventId, 'object' => 'charge', 'payment_intent' => $intentId]],
    ];
}

function chargeDisputeCreatedPayload(string $eventId, string $intentId, string $reason = 'fraudulent'): array
{
    return [
        'id' => $eventId,
        'object' => 'event',
        'api_version' => '2024-01-01',
        'created' => time(),
        'type' => 'charge.dispute.created',
        'data' => ['object' => [
            'id' => 'dp_'.$eventId,
            'object' => 'dispute',
            'payment_intent' => $intentId,
            'reason' => $reason,
        ]],
    ];
}

function transferPayload(string $eventId, string $transferId, bool $succeeded): array
{
    return [
        'id' => $eventId,
        'object' => 'event',
        'api_version' => '2024-01-01',
        'created' => time(),
        'type' => $succeeded ? 'transfer.paid' : 'transfer.failed',
        'data' => ['object' => ['id' => $transferId, 'object' => 'transfer']],
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

it('marks a payment failed on payment_intent.payment_failed and is idempotent on replay', function () {
    [, , $payment] = acceptedBookingWithPendingPayment();

    $event = paymentIntentFailedPayload('evt_pi_failed_1', $payment->stripe_payment_intent_id);

    postSignedStripeWebhook($event)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);
    expect(AuditLog::query()->where('action', 'payment.failed')->count())->toBe(1);

    // Idempotency guard inside MarkPaymentFailed: a second distinct event id
    // describing the same intent must not write a second audit row.
    forgetAuthGuards();
    $secondEvent = paymentIntentFailedPayload('evt_pi_failed_2', $payment->stripe_payment_intent_id);
    postSignedStripeWebhook($secondEvent)->assertOk();

    expect(AuditLog::query()->where('action', 'payment.failed')->count())->toBe(1);
});

it('marks a succeeded payment refunded on charge.refunded, settles a pending Refund row, and updates the booking', function () {
    [, $booking, $payment] = acceptedBookingWithPendingPayment();
    $payment->update(['status' => PaymentStatus::Succeeded]);
    $booking->update(['payment_status' => BookingPaymentStatus::Paid]);

    $refund = Refund::factory()->pending()->create(['payment_id' => $payment->id]);

    $event = chargeRefundedPayload('evt_refund_1', $payment->stripe_payment_intent_id);

    postSignedStripeWebhook($event)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
    expect($refund->fresh()->status)->toBe(RefundStatus::Succeeded);
    expect($booking->fresh()->payment_status)->toBe(BookingPaymentStatus::Refunded);
    expect(AuditLog::query()->where('action', 'payment.refund_confirmed')->count())->toBe(1);

    // Replaying with a different event id describing the same intent must
    // be a no-op — RecordChargeRefund's own guard, independent of the
    // stripe_events dedupe.
    forgetAuthGuards();
    $secondEvent = chargeRefundedPayload('evt_refund_2', $payment->stripe_payment_intent_id);
    postSignedStripeWebhook($secondEvent)->assertOk();

    expect(AuditLog::query()->where('action', 'payment.refund_confirmed')->count())->toBe(1);
    expect(Refund::query()->where('payment_id', $payment->id)->count())->toBe(1);
});

it('creates exactly one Dispute on charge.dispute.created and is idempotent on replay', function () {
    [$customer, , $payment] = acceptedBookingWithPendingPayment();
    $payment->update(['status' => PaymentStatus::Succeeded]);

    $event = chargeDisputeCreatedPayload('evt_dispute_1', $payment->stripe_payment_intent_id, 'fraudulent');

    postSignedStripeWebhook($event)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Disputed);

    $dispute = Dispute::query()
        ->where('disputable_type', $payment->payable_type)
        ->where('disputable_id', $payment->payable_id)
        ->first();

    expect($dispute)->not->toBeNull();
    expect($dispute->status)->toBe(DisputeStatus::Open);
    expect($dispute->raised_by)->toBe($customer->id);
    expect(Dispute::query()->count())->toBe(1);

    // Same underlying payment intent disputed again via a different event id
    // (e.g. Stripe redelivering under a fresh id) must not create a second
    // open Dispute for the same payable.
    forgetAuthGuards();
    $secondEvent = chargeDisputeCreatedPayload('evt_dispute_2', $payment->stripe_payment_intent_id, 'fraudulent');
    postSignedStripeWebhook($secondEvent)->assertOk();

    expect(Dispute::query()->count())->toBe(1);
});

it('ignores transfer.paid — settlement confirmation only, no state change', function () {
    [, , $payment] = acceptedBookingWithPendingPayment();
    $payment->update(['status' => PaymentStatus::Succeeded, 'stripe_transfer_id' => 'tr_ok_1']);

    $event = transferPayload('evt_transfer_paid_1', 'tr_ok_1', succeeded: true);

    postSignedStripeWebhook($event)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
    expect(AuditLog::query()->where('action', 'payment.transfer_failed')->count())->toBe(0);
});

it('flags a payment failed on transfer.failed and is idempotent once already Failed', function () {
    [, , $payment] = acceptedBookingWithPendingPayment();
    $payment->update(['status' => PaymentStatus::Succeeded, 'stripe_transfer_id' => 'tr_bad_1']);

    $event = transferPayload('evt_transfer_failed_1', 'tr_bad_1', succeeded: false);

    postSignedStripeWebhook($event)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);
    expect(AuditLog::query()->where('action', 'payment.transfer_failed')->count())->toBe(1);

    // ReconcileTransfer's own guard: calling handle() again once the
    // payment is already Failed must not throw or double-write the audit
    // trail, even under a fresh event id for the same transfer.
    forgetAuthGuards();
    $secondEvent = transferPayload('evt_transfer_failed_2', 'tr_bad_1', succeeded: false);
    postSignedStripeWebhook($secondEvent)->assertOk();

    expect(AuditLog::query()->where('action', 'payment.transfer_failed')->count())->toBe(1);
});

it('no-ops transfer.failed when no payment matches the transfer id', function () {
    $event = transferPayload('evt_transfer_failed_orphan', 'tr_unknown_999', succeeded: false);

    postSignedStripeWebhook($event)->assertOk();

    expect(AuditLog::query()->where('action', 'payment.transfer_failed')->count())->toBe(0);
});
