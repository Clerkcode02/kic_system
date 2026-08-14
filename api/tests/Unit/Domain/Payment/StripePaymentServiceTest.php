<?php

declare(strict_types=1);

use App\Domain\Payment\Services\StripePaymentService;
use App\Support\ValueObjects\Money;
use Stripe\ApiRequestor;
use Stripe\StripeClient;
use Tests\Support\FakeStripeHttpClient;

/**
 * CLAUDE.md §7: booking payments are destination charges, milestone
 * payments are escrow (no transfer_data), and every intent is CAD — a
 * constant, never a caller-supplied parameter. Uses Stripe's own documented
 * test seam (a fake HttpClient installed via ApiRequestor::setHttpClient),
 * so the real request-building code runs and no network call ever happens.
 */
afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

it('creates a booking payment intent as a CAD destination charge with the application fee', function () {
    $http = new FakeStripeHttpClient([
        ['body' => ['id' => 'pi_123', 'object' => 'payment_intent', 'client_secret' => 'pi_123_secret_abc']],
    ]);
    ApiRequestor::setHttpClient($http);

    $service = new StripePaymentService(new StripeClient('sk_test_fake'));

    $result = $service->createBookingPaymentIntent(
        Money::fromDecimal('196.80', 'CAD'),
        Money::fromDecimal('16.00', 'CAD'),
        'acct_test123',
        ['booking_id' => 'b1'],
    );

    expect($result->intentId)->toBe('pi_123');
    expect($result->clientSecret)->toBe('pi_123_secret_abc');

    expect($http->requests)->toHaveCount(1);
    $params = $http->requests[0]['params'];

    expect($params['amount'])->toBe(19680);
    expect($params['currency'])->toBe('cad');
    expect($params['application_fee_amount'])->toBe(1600);
    expect($params['transfer_data']['destination'])->toBe('acct_test123');
    expect($params['metadata']['booking_id'])->toBe('b1');
});

it('creates a milestone escrow payment intent as CAD with no transfer_data', function () {
    $http = new FakeStripeHttpClient([
        ['body' => ['id' => 'pi_escrow_1', 'object' => 'payment_intent', 'client_secret' => 'pi_escrow_1_secret']],
    ]);
    ApiRequestor::setHttpClient($http);

    $service = new StripePaymentService(new StripeClient('sk_test_fake'));

    $result = $service->createMilestonePaymentIntent(
        Money::fromDecimal('500.00', 'CAD'),
        ['milestone_id' => 'm1'],
    );

    expect($result->intentId)->toBe('pi_escrow_1');
    expect($result->clientSecret)->toBe('pi_escrow_1_secret');

    $params = $http->requests[0]['params'];

    expect($params['amount'])->toBe(50000);
    expect($params['currency'])->toBe('cad');
    expect(array_key_exists('transfer_data', $params))->toBeFalse();
    expect(array_key_exists('application_fee_amount', $params))->toBeFalse();
});
