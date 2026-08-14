<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Business\Models\Business;
use Stripe\StripeClient;

/**
 * Connect account onboarding for providers, on the Accounts v2 API
 * (`/v2/core/accounts`) — Stripe no longer recommends v1 Account creation
 * for new integrations. CLAUDE.md §5 — Canada-only launch — so
 * `identity.country`/`defaults.currency` are hardcoded, never a per-request
 * choice.
 *
 * A provider needs two v2 "configurations" on the same Account: `merchant`
 * (accepts card payments — bookings are destination charges, CLAUDE.md §7)
 * and `recipient` (receives Stripe Transfers — milestone escrow release,
 * ReleaseMilestoneEscrow). `configuration.merchant/recipient` is only
 * present on a response when explicitly `include`d — every create/retrieve
 * call below asks for both.
 */
final class StripeConnectService
{
    private const INCLUDE_CONFIGURATIONS = ['configuration.merchant', 'configuration.recipient'];

    public function __construct(private readonly StripeClient $stripe)
    {
    }

    public function ensureAccount(Business $business): string
    {
        if ($business->stripe_connect_account_id !== null) {
            return $business->stripe_connect_account_id;
        }

        $account = $this->stripe->v2->core->accounts->create([
            'contact_email' => $business->user->email,
            'display_name' => $business->legal_name,
            'dashboard' => 'express',
            'identity' => [
                'country' => 'CA',
                'entity_type' => 'company',
            ],
            'defaults' => [
                'currency' => 'cad',
                // The only {dashboard, fees_collector, losses_collector}
                // permutation Stripe accepts for an Express-equivalent
                // account (verified against the live API — most
                // combinations return "account configuration is not
                // supported" with no further detail): the platform collects
                // both the application fee and negative-balance risk, same
                // liability shape v1's `type: 'express'` had.
                'responsibilities' => [
                    'fees_collector' => 'application',
                    'losses_collector' => 'application',
                ],
            ],
            'configuration' => [
                'merchant' => [
                    'capabilities' => [
                        'card_payments' => ['requested' => true],
                    ],
                ],
                'recipient' => [
                    'capabilities' => [
                        'stripe_balance' => [
                            'stripe_transfers' => ['requested' => true],
                        ],
                    ],
                ],
            ],
            'include' => self::INCLUDE_CONFIGURATIONS,
        ]);

        $business->update(['stripe_connect_account_id' => $account->id]);

        return $account->id;
    }

    public function createOnboardingLink(Business $business, string $refreshUrl, string $returnUrl): string
    {
        $accountId = $this->ensureAccount($business);

        $link = $this->stripe->v2->core->accountLinks->create([
            'account' => $accountId,
            'use_case' => [
                'type' => 'account_onboarding',
                'account_onboarding' => [
                    'configurations' => ['merchant', 'recipient'],
                    'refresh_url' => $refreshUrl,
                    'return_url' => $returnUrl,
                ],
            ],
        ]);

        return $link->url;
    }

    /**
     * @return array{charges_enabled: bool, payouts_enabled: bool}
     */
    public function syncStatus(Business $business): array
    {
        if ($business->stripe_connect_account_id === null) {
            return ['charges_enabled' => false, 'payouts_enabled' => false];
        }

        $account = $this->stripe->v2->core->accounts->retrieve($business->stripe_connect_account_id, [
            'include' => self::INCLUDE_CONFIGURATIONS,
        ]);

        $status = [
            'charges_enabled' => ($account->configuration->merchant->capabilities->card_payments->status ?? null) === 'active',
            'payouts_enabled' => ($account->configuration->recipient->capabilities->stripe_balance->stripe_transfers->status ?? null) === 'active',
        ];

        $business->update([
            'stripe_charges_enabled' => $status['charges_enabled'],
            'stripe_payouts_enabled' => $status['payouts_enabled'],
        ]);

        return $status;
    }
}
