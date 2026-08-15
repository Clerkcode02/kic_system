<?php

declare(strict_types=1);

use App\Support\Auditable;

/**
 * SRS §13 / CLAUDE.md §12 "Audit trail": every event representing an
 * approval, suspension, refund, payout, status transition, or admin
 * override must implement Auditable so RecordAuditEntry captures it. This
 * enumerates that critical set explicitly (rather than only asserting on
 * whatever happens to implement the interface today) so a newly added
 * critical event that forgets to implement Auditable fails this test
 * instead of silently going unaudited.
 */
it('implements Auditable on every event representing a critical, auditable action', function () {
    $criticalEvents = [
        // Booking
        \App\Domain\Booking\Events\BookingCreated::class,
        \App\Domain\Booking\Events\BookingStatusChanged::class,
        // Quotation
        \App\Domain\Quotation\Events\QuotationSent::class,
        \App\Domain\Quotation\Events\QuotationAccepted::class,
        \App\Domain\Quotation\Events\QuotationRejected::class,
        \App\Domain\Quotation\Events\QuotationRevised::class,
        \App\Domain\Quotation\Events\QuotationExpired::class,
        // Business verification (admin approval/rejection)
        \App\Domain\Business\Events\BusinessSubmittedForVerification::class,
        \App\Domain\Business\Events\BusinessVerificationApproved::class,
        \App\Domain\Business\Events\BusinessVerificationRejected::class,
        // Freelancer verification (admin approval/rejection)
        \App\Domain\Freelance\Events\FreelancerVerificationApproved::class,
        \App\Domain\Freelance\Events\FreelancerVerificationRejected::class,
        // Freelance project / proposal / contract / milestone lifecycle
        \App\Domain\Freelance\Events\ProjectPublished::class,
        \App\Domain\Freelance\Events\ProjectStatusChanged::class,
        \App\Domain\Freelance\Events\ProjectScopeUpdated::class,
        \App\Domain\Freelance\Events\ProposalSubmitted::class,
        \App\Domain\Freelance\Events\ProposalStatusChanged::class,
        \App\Domain\Freelance\Events\FreelancerHired::class,
        \App\Domain\Freelance\Events\ContractMilestonesDefined::class,
        \App\Domain\Freelance\Events\ContractCompleted::class,
        \App\Domain\Freelance\Events\MilestoneStatusChanged::class,
        // Payments / payouts / refunds
        \App\Domain\Payment\Events\PaymentSucceeded::class,
        \App\Domain\Payment\Events\PayoutCompleted::class,
        \App\Domain\Payment\Events\RefundProcessed::class,
        // Reviews
        \App\Domain\Review\Events\ReviewReceived::class,
        // Disputes (admin resolution authority)
        \App\Domain\Dispute\Events\DisputeRaised::class,
        \App\Domain\Dispute\Events\DisputeResolved::class,
    ];

    foreach ($criticalEvents as $eventClass) {
        expect(class_exists($eventClass))->toBeTrue("Expected critical event class {$eventClass} to exist.");
        expect(is_a($eventClass, Auditable::class, true))
            ->toBeTrue("Expected {$eventClass} to implement Auditable so RecordAuditEntry captures it.");
    }
});

it('registers RecordAuditEntry against the Auditable contract in AppServiceProvider', function () {
    expect(\Illuminate\Support\Facades\Event::hasListeners(Auditable::class))->toBeTrue();
});
