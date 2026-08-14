<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Business\Models\Business;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Support\Action;

/**
 * Reacts to `account.updated`. Stripe pushes charges_enabled/payouts_enabled
 * changes as they happen (onboarding completed, a requirement lapsed, …) —
 * this keeps the connected account's gating columns current without
 * requiring GET /providers/me/stripe/status to be polled.
 */
final class SyncConnectAccountStatus implements Action
{
    public function handle(string $stripeAccountId, bool $chargesEnabled, bool $payoutsEnabled): void
    {
        $business = Business::query()->where('stripe_connect_account_id', $stripeAccountId)->first();

        if ($business !== null) {
            $business->update([
                'stripe_charges_enabled' => $chargesEnabled,
                'stripe_payouts_enabled' => $payoutsEnabled,
            ]);

            return;
        }

        $freelancer = FreelancerProfile::query()->where('stripe_connect_account_id', $stripeAccountId)->first();

        $freelancer?->update([
            'stripe_charges_enabled' => $chargesEnabled,
            'stripe_payouts_enabled' => $payoutsEnabled,
        ]);
    }
}
