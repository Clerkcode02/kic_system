<?php

declare(strict_types=1);

use App\Domain\Booking\Events\BookingCreated;
use App\Domain\Booking\Events\BookingStatusChanged;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Notifications\BookingCreatedNotification;
use App\Domain\Business\Actions\ApproveBusinessVerification;
use App\Domain\Business\Actions\RejectBusinessVerification;
use App\Domain\Business\Models\Business;
use App\Domain\Freelance\Actions\ApproveFreelancerVerification;
use App\Domain\Freelance\Actions\RejectFreelancerVerification;
use App\Domain\Freelance\Events\FreelancerHired;
use App\Domain\Freelance\Events\MilestoneStatusChanged;
use App\Domain\Freelance\Events\ProposalSubmitted;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Notification\Notifications\VerificationApprovedNotification;
use App\Domain\Notification\Notifications\VerificationRejectedNotification;
use App\Domain\Payment\Actions\CapturePayment;
use App\Domain\Payment\Actions\IssueRefund;
use App\Domain\Payment\Jobs\RunProviderPayoutJob;
use App\Domain\Payment\Models\Payment;
use App\Domain\Quotation\Events\QuotationAccepted;
use App\Domain\Quotation\Events\QuotationExpiryReminderDue;
use App\Domain\Quotation\Events\QuotationRejected;
use App\Domain\Quotation\Events\QuotationSent;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\Review\Actions\SubmitReview;
use App\Domain\User\Models\User;
use App\Support\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

uses(RefreshDatabase::class);

/**
 * @param  class-string  $notificationClass
 */
function notificationCount(User $user, string $notificationClass): int
{
    return DatabaseNotification::query()
        ->where('notifiable_id', $user->id)
        ->where('type', $notificationClass)
        ->count();
}

it('still delivers the in-app notification when a user has opted out of email', function () {
    $provider = User::factory()->provider()->create();
    Business::factory()->verified()->create(['user_id' => $provider->id]);

    NotificationPreference::create([
        'user_id' => $provider->id,
        'category' => 'booking',
        'email' => false,
        'push_web' => true,
    ]);

    Mail::fake();

    $booking = Booking::factory()->create(['provider_id' => $provider->business->id]);

    BookingCreated::dispatch($booking);

    expect(notificationCount($provider, BookingCreatedNotification::class))->toBe(1);
    Mail::assertNothingSent();
});

it('does not let a mail channel exception block the database channel write', function () {
    Mail::extend('throwing', fn () => new class () extends AbstractTransport {
        public function __toString(): string
        {
            return 'throwing://test';
        }

        protected function doSend(SentMessage $message): void
        {
            throw new TransportException('Simulated mail transport failure.');
        }
    });
    config(['mail.mailers.throwing' => ['transport' => 'throwing'], 'mail.default' => 'throwing']);

    $provider = User::factory()->provider()->create();
    Business::factory()->verified()->create(['user_id' => $provider->id]);
    $booking = Booking::factory()->create(['provider_id' => $provider->business->id]);

    BookingCreated::dispatch($booking);

    expect(notificationCount($provider, BookingCreatedNotification::class))->toBe(1);
});

it('sends exactly one BookingCreatedNotification to the provider', function () {
    $provider = User::factory()->provider()->create();
    Business::factory()->verified()->create(['user_id' => $provider->id]);
    $booking = Booking::factory()->create(['provider_id' => $provider->business->id]);

    BookingCreated::dispatch($booking);

    expect(notificationCount($provider, \App\Domain\Booking\Notifications\BookingCreatedNotification::class))->toBe(1);
});

it('sends exactly one BookingAcceptedNotification to the provider', function () {
    $provider = User::factory()->provider()->create();
    Business::factory()->verified()->create(['user_id' => $provider->id]);
    $booking = Booking::factory()->create(['provider_id' => $provider->business->id]);

    BookingStatusChanged::dispatch($booking, 'accepted', 'accepted', null);

    expect(notificationCount($provider, \App\Domain\Booking\Notifications\BookingAcceptedNotification::class))->toBe(1);
});

it('sends exactly one BookingCancelledNotification to the customer', function () {
    $provider = User::factory()->provider()->create();
    Business::factory()->verified()->create(['user_id' => $provider->id]);
    $booking = Booking::factory()->create(['provider_id' => $provider->business->id]);

    BookingStatusChanged::dispatch($booking, 'scheduled', 'cancelled', null);

    expect(notificationCount($booking->customer, \App\Domain\Booking\Notifications\BookingCancelledNotification::class))->toBe(1);
});

it('sends exactly one QuotationSentNotification to the customer', function () {
    $quotation = Quotation::factory()->sent()->create();

    QuotationSent::dispatch($quotation);

    expect(notificationCount($quotation->booking->customer, \App\Domain\Quotation\Notifications\QuotationSentNotification::class))->toBe(1);
});

it('sends exactly one QuotationAcceptedNotification to the provider', function () {
    $provider = User::factory()->provider()->create();
    Business::factory()->verified()->create(['user_id' => $provider->id]);
    $booking = Booking::factory()->create(['provider_id' => $provider->business->id]);
    $quotation = Quotation::factory()->accepted()->create(['booking_id' => $booking->id]);
    $payment = Payment::factory()->succeeded()->forBooking($booking)->create();

    QuotationAccepted::dispatch($quotation, $payment);

    expect(notificationCount($provider, \App\Domain\Quotation\Notifications\QuotationAcceptedNotification::class))->toBe(1);
});

it('sends exactly one QuotationRejectedNotification to the provider', function () {
    $provider = User::factory()->provider()->create();
    Business::factory()->verified()->create(['user_id' => $provider->id]);
    $booking = Booking::factory()->create(['provider_id' => $provider->business->id]);
    $quotation = Quotation::factory()->rejected()->create(['booking_id' => $booking->id]);

    QuotationRejected::dispatch($quotation);

    expect(notificationCount($provider, \App\Domain\Quotation\Notifications\QuotationRejectedNotification::class))->toBe(1);
});

it('sends exactly one QuotationExpiringNotification to the customer', function () {
    $quotation = Quotation::factory()->sent()->create();

    QuotationExpiryReminderDue::dispatch($quotation, 24);

    expect(notificationCount($quotation->booking->customer, \App\Domain\Quotation\Notifications\QuotationExpiringNotification::class))->toBe(1);
});

it('sends exactly one PaymentSucceededNotification to the payer', function () {
    $booking = Booking::factory()->create();
    $payment = Payment::factory()->pending()->forBooking($booking)->create();

    app(CapturePayment::class)->handle((string) $payment->stripe_payment_intent_id);

    expect(notificationCount($booking->customer, \App\Domain\Payment\Notifications\PaymentSucceededNotification::class))->toBe(1);
});

it('sends exactly one RefundProcessedNotification to the payer', function () {
    $booking = Booking::factory()->create();
    $payment = Payment::factory()->succeeded()->forBooking($booking)->create([
        'amount' => Money::fromDecimal('100.00', 'CAD'),
    ]);
    $admin = User::factory()->admin()->create();

    app(IssueRefund::class)->handle($payment, $admin, null, 'Customer requested refund.');

    expect(notificationCount($booking->customer, \App\Domain\Payment\Notifications\RefundProcessedNotification::class))->toBe(1);
});

it('sends exactly one ProposalReceivedNotification to the project client', function () {
    $proposal = Proposal::factory()->submitted()->create();

    ProposalSubmitted::dispatch($proposal);

    expect(notificationCount($proposal->project->client, \App\Domain\Freelance\Notifications\ProposalReceivedNotification::class))->toBe(1);
});

it('sends exactly one FreelancerHiredNotification to the freelancer', function () {
    $contract = Contract::factory()->create();

    FreelancerHired::dispatch($contract);

    expect(notificationCount($contract->proposal->freelancer->user, \App\Domain\Freelance\Notifications\FreelancerHiredNotification::class))->toBe(1);
});

it('sends exactly one MilestoneSubmittedNotification to the project client', function () {
    $milestone = Milestone::factory()->submitted()->create();

    MilestoneStatusChanged::dispatch($milestone, 'pending', 'submitted', null);

    expect(notificationCount($milestone->contract->project->client, \App\Domain\Freelance\Notifications\MilestoneSubmittedNotification::class))->toBe(1);
});

it('sends exactly one MilestoneApprovedNotification to the freelancer', function () {
    $milestone = Milestone::factory()->approved()->create();

    MilestoneStatusChanged::dispatch($milestone, 'submitted', 'approved', null);

    expect(notificationCount($milestone->contract->proposal->freelancer->user, \App\Domain\Freelance\Notifications\MilestoneApprovedNotification::class))->toBe(1);
});

it('sends exactly one ReviewReceivedNotification to the reviewee', function () {
    $reviewer = User::factory()->customer()->create();
    $reviewee = User::factory()->provider()->create();
    $booking = Booking::factory()->create();

    app(SubmitReview::class)->handle($reviewer, $reviewee, $booking, 5, 'Great work!');

    expect(notificationCount($reviewee, \App\Domain\Review\Notifications\ReviewReceivedNotification::class))->toBe(1);
});

it('sends exactly one PayoutCompletedNotification to the provider', function () {
    $provider = User::factory()->provider()->create();
    $business = Business::factory()->verified()->create(['user_id' => $provider->id]);
    $booking = Booking::factory()->create(['provider_id' => $business->id]);
    Payment::factory()->succeeded()->forBooking($booking)->create();

    app(RunProviderPayoutJob::class)->handle();

    expect(notificationCount($provider, \App\Domain\Payment\Notifications\PayoutCompletedNotification::class))->toBe(1);
});

it('sends exactly one VerificationApprovedNotification when a business is approved', function () {
    $provider = User::factory()->provider()->create();
    $business = Business::factory()->pending()->create(['user_id' => $provider->id]);

    app(ApproveBusinessVerification::class)->handle($business);

    expect(notificationCount($provider, VerificationApprovedNotification::class))->toBe(1);
});

it('sends exactly one VerificationRejectedNotification when a business is rejected', function () {
    $provider = User::factory()->provider()->create();
    $business = Business::factory()->pending()->create(['user_id' => $provider->id]);

    app(RejectBusinessVerification::class)->handle($business, 'Missing documents.');

    expect(notificationCount($provider, VerificationRejectedNotification::class))->toBe(1);
});

it('sends exactly one VerificationApprovedNotification when a freelancer is approved', function () {
    $freelancer = User::factory()->freelancer()->create();
    $profile = FreelancerProfile::factory()->pending()->create(['user_id' => $freelancer->id]);

    app(ApproveFreelancerVerification::class)->handle($profile);

    expect(notificationCount($freelancer, VerificationApprovedNotification::class))->toBe(1);
});

it('sends exactly one VerificationRejectedNotification when a freelancer is rejected', function () {
    $freelancer = User::factory()->freelancer()->create();
    $profile = FreelancerProfile::factory()->pending()->create(['user_id' => $freelancer->id]);

    app(RejectFreelancerVerification::class)->handle($profile, 'Incomplete portfolio.');

    expect(notificationCount($freelancer, VerificationRejectedNotification::class))->toBe(1);
});
