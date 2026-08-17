<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Enums\BusinessVerificationStatus;
use App\Domain\Business\Models\Business;
use App\Domain\Dispute\Enums\DisputeStatus;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Freelance\Enums\FreelancerApprovalStatus;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\User;
use Illuminate\Support\Carbon;

/**
 * Computes the metrics set persisted by GenerateAdminAnalyticsSnapshotJob
 * every hour (SRS §12 "analytics dashboard... served from the hourly
 * snapshot table, not live aggregation"). Kept as its own query object so
 * the job stays a thin scheduling wrapper.
 */
final class ComputeAdminAnalyticsMetricsQuery
{
    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $since24h = Carbon::now()->subDay();

        $gmv = (float) Payment::on('pgsql_read')
            ->whereIn('payable_type', ['booking', 'milestone'])
            ->where('status', PaymentStatus::Succeeded)
            ->where('created_at', '>=', $since24h)
            ->get()
            ->sum(fn (Payment $payment) => $payment->amount->toDecimal());

        $payoutVolume = (float) Payment::on('pgsql_read')
            ->where('status', PaymentStatus::Succeeded)
            ->whereNotNull('stripe_transfer_id')
            ->where('created_at', '>=', $since24h)
            ->get()
            ->sum(fn (Payment $payment) => $payment->provider_net_amount?->toDecimal() ?? 0.0);

        return [
            'bookings_total' => Booking::on('pgsql_read')->count(),
            'bookings_active_24h' => Booking::on('pgsql_read')->where('created_at', '>=', $since24h)->count(),
            'gmv_24h' => $gmv,
            'new_signups_24h' => [
                'customer' => User::on('pgsql_read')->role('customer')->where('created_at', '>=', $since24h)->count(),
                'provider' => User::on('pgsql_read')->role(['provider_owner', 'provider_staff'])->where('created_at', '>=', $since24h)->count(),
                'freelancer' => User::on('pgsql_read')->role('freelancer')->where('created_at', '>=', $since24h)->count(),
            ],
            'verification_queue_depth' => Business::on('pgsql_read')->where('verification_status', BusinessVerificationStatus::Pending)->count()
                + FreelancerProfile::on('pgsql_read')->where('approval_status', FreelancerApprovalStatus::Pending)->count(),
            'open_disputes' => Dispute::on('pgsql_read')->whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview])->count(),
            'payout_volume_24h' => $payoutVolume,
        ];
    }
}
