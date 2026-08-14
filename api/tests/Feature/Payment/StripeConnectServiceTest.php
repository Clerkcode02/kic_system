<?php

declare(strict_types=1);

use App\Domain\Business\Models\Business;
use App\Domain\Payment\Services\StripeConnectService;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\StripeClient;
use Tests\Support\FakeStripeHttpClient;

uses(RefreshDatabase::class);

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

/**
 * CLAUDE.md §5/§7 — Canada-only launch: Connect account creation must
 * always request a CA account with CAD as the default currency, never a
 * per-request choice. Uses the Accounts v2 API (`/v2/core/accounts`) —
 * Stripe no longer recommends v1 account creation for new integrations.
 */
it('creates a CA Express connected account with CAD default currency', function () {
    $user = User::factory()->provider()->create(['email' => 'owner@example.com']);
    $business = Business::factory()->create(['user_id' => $user->id, 'stripe_connect_account_id' => null]);

    $http = new FakeStripeHttpClient([
        ['body' => ['id' => 'acct_new_1', 'object' => 'v2.core.account']],
        ['body' => ['object' => 'v2.core.account_link', 'url' => 'https://connect.stripe.com/setup/test']],
    ]);
    ApiRequestor::setHttpClient($http);

    $service = new StripeConnectService(new StripeClient('sk_test_fake'));

    $url = $service->createOnboardingLink($business, 'https://app.example.com/refresh', 'https://app.example.com/return');

    expect($url)->toBe('https://connect.stripe.com/setup/test');
    expect($business->fresh()->stripe_connect_account_id)->toBe('acct_new_1');

    $accountCreateParams = $http->requests[0]['params'];
    expect($http->requests[0]['absUrl'])->toContain('/v2/core/accounts');
    expect($accountCreateParams['identity']['country'])->toBe('CA');
    expect($accountCreateParams['defaults']['currency'])->toBe('cad');
    expect($accountCreateParams['contact_email'])->toBe('owner@example.com');
    expect($accountCreateParams['configuration']['merchant']['capabilities']['card_payments']['requested'])->toBeTrue();
    expect($accountCreateParams['configuration']['recipient']['capabilities']['stripe_balance']['stripe_transfers']['requested'])->toBeTrue();

    $linkParams = $http->requests[1]['params'];
    expect($http->requests[1]['absUrl'])->toContain('/v2/core/account_links');
    expect($linkParams['account'])->toBe('acct_new_1');
    expect($linkParams['use_case']['type'])->toBe('account_onboarding');
    expect($linkParams['use_case']['account_onboarding']['refresh_url'])->toBe('https://app.example.com/refresh');
    expect($linkParams['use_case']['account_onboarding']['return_url'])->toBe('https://app.example.com/return');
});

it('syncs charges_enabled and payouts_enabled from the connected account', function () {
    $user = User::factory()->provider()->create();
    $business = Business::factory()->create([
        'user_id' => $user->id,
        'stripe_connect_account_id' => 'acct_existing',
        'stripe_charges_enabled' => false,
        'stripe_payouts_enabled' => false,
    ]);

    $http = new FakeStripeHttpClient([
        ['body' => [
            'id' => 'acct_existing',
            'object' => 'v2.core.account',
            'configuration' => [
                'merchant' => ['applied' => true, 'capabilities' => ['card_payments' => ['status' => 'active']]],
                'recipient' => ['applied' => true, 'capabilities' => ['stripe_balance' => ['stripe_transfers' => ['status' => 'active']]]],
            ],
        ]],
    ]);
    ApiRequestor::setHttpClient($http);

    $service = new StripeConnectService(new StripeClient('sk_test_fake'));

    $status = $service->syncStatus($business);

    expect($status)->toBe(['charges_enabled' => true, 'payouts_enabled' => true]);
    expect($business->fresh()->stripe_payouts_enabled)->toBeTrue();
    expect($business->fresh()->stripe_charges_enabled)->toBeTrue();
    expect($http->requests[0]['absUrl'])->toContain('/v2/core/accounts/acct_existing');
});

it('treats a restricted (not yet onboarded) capability status as not enabled', function () {
    $user = User::factory()->provider()->create();
    $business = Business::factory()->create([
        'user_id' => $user->id,
        'stripe_connect_account_id' => 'acct_fresh',
    ]);

    $http = new FakeStripeHttpClient([
        ['body' => [
            'id' => 'acct_fresh',
            'object' => 'v2.core.account',
            'configuration' => [
                'merchant' => ['applied' => true, 'capabilities' => ['card_payments' => ['status' => 'restricted']]],
                'recipient' => ['applied' => true, 'capabilities' => ['stripe_balance' => ['stripe_transfers' => ['status' => 'restricted']]]],
            ],
        ]],
    ]);
    ApiRequestor::setHttpClient($http);

    $service = new StripeConnectService(new StripeClient('sk_test_fake'));

    $status = $service->syncStatus($business);

    expect($status)->toBe(['charges_enabled' => false, 'payouts_enabled' => false]);
});
