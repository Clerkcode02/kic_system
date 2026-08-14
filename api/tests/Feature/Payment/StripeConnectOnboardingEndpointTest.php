<?php

declare(strict_types=1);

use App\Domain\Business\Models\Business;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Tests\Support\FakeStripeHttpClient;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

function ownerWithBusiness(): array
{
    $user = User::factory()->provider()->create();
    $user->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->create(['user_id' => $user->id]);

    return [$user, $business];
}

it('requests a CA onboarding link and stores the connected account id', function () {
    [$owner, $business] = ownerWithBusiness();

    $http = new FakeStripeHttpClient([
        ['body' => ['id' => 'acct_endpoint_1', 'object' => 'v2.core.account']],
        ['body' => ['object' => 'v2.core.account_link', 'url' => 'https://connect.stripe.com/setup/endpoint']],
    ]);
    ApiRequestor::setHttpClient($http);

    $this->withHeaders(authHeader($owner))
        ->postJson('/api/v1/provider/me/stripe/onboarding-link')
        ->assertOk()
        ->assertJsonPath('data.url', 'https://connect.stripe.com/setup/endpoint');

    expect($business->fresh()->stripe_connect_account_id)->toBe('acct_endpoint_1');

    $accountCreateParams = $http->requests[0]['params'];
    expect($accountCreateParams['identity']['country'])->toBe('CA');
    expect($accountCreateParams['defaults']['currency'])->toBe('cad');
});

it('reports charges_enabled/payouts_enabled from Stripe on the status endpoint', function () {
    [$owner, $business] = ownerWithBusiness();
    $business->update(['stripe_connect_account_id' => 'acct_status_1']);

    $http = new FakeStripeHttpClient([
        ['body' => [
            'id' => 'acct_status_1',
            'object' => 'v2.core.account',
            'configuration' => [
                'merchant' => ['applied' => true, 'capabilities' => ['card_payments' => ['status' => 'active']]],
                'recipient' => ['applied' => true, 'capabilities' => ['stripe_balance' => ['stripe_transfers' => ['status' => 'restricted']]]],
            ],
        ]],
    ]);
    ApiRequestor::setHttpClient($http);

    $this->withHeaders(authHeader($owner))
        ->getJson('/api/v1/provider/me/stripe/status')
        ->assertOk()
        ->assertJsonPath('data.charges_enabled', true)
        ->assertJsonPath('data.payouts_enabled', false);
});

it('denies a provider-role user with no business record from requesting an onboarding link', function () {
    $userWithoutBusiness = User::factory()->provider()->create();
    $userWithoutBusiness->assignRole(RoleName::ProviderOwner->value);

    $this->withHeaders(authHeader($userWithoutBusiness))
        ->postJson('/api/v1/provider/me/stripe/onboarding-link')
        ->assertForbidden();
});
