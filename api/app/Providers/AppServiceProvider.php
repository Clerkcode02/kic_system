<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Audit\Listeners\RecordAuditEntry;
use App\Domain\Booking\Events\BookingCreated;
use App\Domain\Booking\Events\BookingStatusChanged;
use App\Domain\Booking\Listeners\SendBookingCreatedNotification;
use App\Domain\Booking\Listeners\SendBookingStatusNotifications;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Events\BusinessSubmittedForVerification;
use App\Domain\Business\Events\BusinessVerificationApproved;
use App\Domain\Business\Events\BusinessVerificationRejected;
use App\Domain\Business\Listeners\NotifyAdminsOfVerificationSubmission;
use App\Domain\Business\Listeners\SendBusinessVerificationApprovedNotification;
use App\Domain\Business\Listeners\SendBusinessVerificationRejectedNotification;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Services\AvailabilityCache;
use App\Domain\Business\Services\GeoapifyGeocoder;
use App\Domain\Business\Services\Geocoder;
use App\Domain\Catalog\Events\CategoryTreeChanged;
use App\Domain\Catalog\Listeners\FlushCategoryTreeCache;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Freelance\Events\FreelancerHired;
use App\Domain\Freelance\Events\FreelancerVerificationApproved;
use App\Domain\Freelance\Events\FreelancerVerificationRejected;
use App\Domain\Freelance\Events\MilestoneStatusChanged;
use App\Domain\Freelance\Events\ProposalSubmitted;
use App\Domain\Freelance\Listeners\SendFreelancerHiredNotification;
use App\Domain\Freelance\Listeners\SendFreelancerVerificationApprovedNotification;
use App\Domain\Freelance\Listeners\SendFreelancerVerificationRejectedNotification;
use App\Domain\Freelance\Listeners\SendMilestoneStatusNotifications;
use App\Domain\Freelance\Listeners\SendProposalReceivedNotification;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\Deliverable;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\Payment\Events\PaymentSucceeded;
use App\Domain\Payment\Events\PayoutAnomalyDetected;
use App\Domain\Payment\Events\PayoutCompleted;
use App\Domain\Payment\Events\RefundProcessed;
use App\Domain\Payment\Events\RefundRateSpikeDetected;
use App\Domain\Payment\Listeners\NotifyAdminsOfPayoutAnomaly;
use App\Domain\Payment\Listeners\NotifyAdminsOfRefundRateSpike;
use App\Domain\Payment\Listeners\SendPaymentSucceededNotification;
use App\Domain\Payment\Listeners\SendPayoutCompletedNotification;
use App\Domain\Payment\Listeners\SendRefundProcessedNotification;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Payout;
use App\Domain\Payment\Models\Refund;
use App\Domain\Payment\Services\PaymentGateway;
use App\Domain\Payment\Services\StripePaymentService;
use App\Domain\Platform\Models\PlatformSetting;
use App\Domain\Quotation\Events\QuotationAccepted;
use App\Domain\Quotation\Events\QuotationExpiryReminderDue;
use App\Domain\Quotation\Events\QuotationRejected;
use App\Domain\Quotation\Events\QuotationSent;
use App\Domain\Quotation\Listeners\SendQuotationAcceptedNotification;
use App\Domain\Quotation\Listeners\SendQuotationExpiringNotification;
use App\Domain\Quotation\Listeners\SendQuotationRejectedNotification;
use App\Domain\Quotation\Listeners\SendQuotationSentNotification;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\Review\Events\ReviewReceived;
use App\Domain\Review\Listeners\SendReviewReceivedNotification;
use App\Domain\Review\Models\Review;
use App\Domain\User\Events\RepeatedFailedLoginsDetected;
use App\Domain\User\Events\UserRegistered;
use App\Domain\User\Listeners\NotifyAdminsOfRepeatedFailedLogins;
use App\Domain\User\Listeners\SendEmailVerificationNotification;
use App\Domain\User\Models\FailedLoginAttempt;
use App\Domain\User\Models\User;
use App\Support\Auditable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Minishlink\WebPush\WebPush;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            Geocoder::class,
            fn () => new GeoapifyGeocoder((string) config('services.geoapify.key'))
        );

        // One Stripe client for the whole app — StripePaymentService and
        // StripeConnectService both depend on this rather than constructing
        // their own, so tests can swap it for a mock (CLAUDE.md §7).
        $this->app->singleton(StripeClient::class, fn () => new StripeClient(
            config('services.stripe.secret') ?: 'sk_test_unconfigured'
        ));

        // Phase 7: swaps the Phase 5 StubPaymentGateway for a Stripe-backed
        // implementation — no domain code changes, only this line
        // (CLAUDE.md §7/§9).
        $this->app->bind(PaymentGateway::class, StripePaymentService::class);

        // WebPush::__construct eagerly validates the 'VAPID' auth entry if
        // present at all, so an unconfigured local/test env (empty keys)
        // must omit the key entirely rather than pass it with nulls — the
        // resulting "no VAPID configured" failure then only surfaces when
        // a send is actually attempted, where NotificationDispatcher's
        // per-channel try/catch already isolates it.
        $this->app->singleton(WebPush::class, function () {
            $publicKey = config('services.vapid.public_key');
            $privateKey = config('services.vapid.private_key');

            $auth = ($publicKey && $privateKey) ? [
                'VAPID' => [
                    'subject' => config('services.vapid.subject'),
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ] : [];

            return new WebPush($auth);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // stripe-php surfaces informational `Stripe-Notice` response headers
        // (e.g. "we recommend building your integration using Accounts v2")
        // by calling trigger_error(..., E_USER_WARNING) — see
        // ApiRequestor::_maybeEmitStripeNotice(). Laravel's default error
        // handler promotes any uncaught PHP warning to a fatal
        // ErrorException, which would otherwise turn a perfectly successful
        // Stripe API call (e.g. Connect account creation) into a 500. Log
        // and swallow only warnings that actually originate from the
        // stripe-php package; every other warning still falls through to
        // Laravel's normal (fatal) handling.
        $previousErrorHandler = set_error_handler(
            function (int $level, string $message, string $file = '', int $line = 0) use (&$previousErrorHandler): bool {
                if ($level === E_USER_WARNING && str_contains($file, DIRECTORY_SEPARATOR.'stripe'.DIRECTORY_SEPARATOR.'stripe-php'.DIRECTORY_SEPARATOR)) {
                    Log::info('Stripe API notice: '.$message);

                    return true;
                }

                return $previousErrorHandler !== null && $previousErrorHandler($level, $message, $file, $line);
            }
        );

        // SRS §13: single global listener for every event implementing the
        // Auditable contract — Laravel's dispatcher also matches listeners
        // registered against an interface the dispatched event implements,
        // so no per-event registration is needed here.
        Event::listen(Auditable::class, RecordAuditEntry::class);

        Event::listen(UserRegistered::class, SendEmailVerificationNotification::class);
        Event::listen(CategoryTreeChanged::class, FlushCategoryTreeCache::class);
        Event::listen(BusinessSubmittedForVerification::class, NotifyAdminsOfVerificationSubmission::class);
        Event::listen(RepeatedFailedLoginsDetected::class, NotifyAdminsOfRepeatedFailedLogins::class);
        Event::listen(PayoutAnomalyDetected::class, NotifyAdminsOfPayoutAnomaly::class);
        Event::listen(RefundRateSpikeDetected::class, NotifyAdminsOfRefundRateSpike::class);

        // SRS §11 notification workflow — one queued listener per event,
        // each building its Notification and sending through
        // NotificationDispatcher (per-channel try/catch isolation).
        Event::listen(BookingCreated::class, SendBookingCreatedNotification::class);
        Event::listen(BookingStatusChanged::class, SendBookingStatusNotifications::class);
        Event::listen(QuotationSent::class, SendQuotationSentNotification::class);
        Event::listen(QuotationAccepted::class, SendQuotationAcceptedNotification::class);
        Event::listen(QuotationRejected::class, SendQuotationRejectedNotification::class);
        Event::listen(QuotationExpiryReminderDue::class, SendQuotationExpiringNotification::class);
        Event::listen(PaymentSucceeded::class, SendPaymentSucceededNotification::class);
        Event::listen(RefundProcessed::class, SendRefundProcessedNotification::class);
        Event::listen(PayoutCompleted::class, SendPayoutCompletedNotification::class);
        Event::listen(ProposalSubmitted::class, SendProposalReceivedNotification::class);
        Event::listen(FreelancerHired::class, SendFreelancerHiredNotification::class);
        Event::listen(MilestoneStatusChanged::class, SendMilestoneStatusNotifications::class);
        Event::listen(ReviewReceived::class, SendReviewReceivedNotification::class);
        Event::listen(BusinessVerificationApproved::class, SendBusinessVerificationApprovedNotification::class);
        Event::listen(BusinessVerificationRejected::class, SendBusinessVerificationRejectedNotification::class);
        Event::listen(FreelancerVerificationApproved::class, SendFreelancerVerificationApprovedNotification::class);
        Event::listen(FreelancerVerificationRejected::class, SendFreelancerVerificationRejectedNotification::class);

        // Provider availability is cached per-provider-per-day (5 min TTL);
        // any booking write for a date must bust that date's cache entry
        // immediately rather than waiting out the TTL (CLAUDE.md
        // provider-management spec). A plain model-event hook, not a domain
        // event/listener pair, since this is cache bookkeeping rather than a
        // business-rule side effect.
        Booking::created(function (Booking $booking) {
            $this->app->make(AvailabilityCache::class)->forget($booking->provider_id, $booking->scheduled_date->toDateString());
        });

        Booking::updated(function (Booking $booking) {
            if ($booking->wasChanged(['scheduled_date', 'status', 'time_slot_start', 'time_slot_end'])) {
                $this->app->make(AvailabilityCache::class)->forget($booking->provider_id, $booking->scheduled_date->toDateString());

                $originalDate = $booking->getOriginal('scheduled_date')?->toDateString();

                if ($originalDate !== null && $originalDate !== $booking->scheduled_date->toDateString()) {
                    $this->app->make(AvailabilityCache::class)->forget($booking->provider_id, $originalDate);
                }
            }
        });

        // Tighter than the general API — auth endpoints are the brute-force
        // surface (login, register). Keyed by IP + submitted email so one
        // abusive IP can't be sidestepped by rotating addresses.
        RateLimiter::for('auth', function (Request $request) {
            $identifier = (string) $request->input('email', '');

            return Limit::perMinute(10)->by($request->ip().'|'.$identifier);
        });

        // SRS §17 — payments must be measurably tighter than the general API
        // surface (which currently has no dedicated limiter at all, i.e.
        // effectively unbounded beyond web-server/infra limits). Keyed by
        // user id when authenticated (every route this applies to sits
        // behind api.protected) and falls back to IP for safety.
        RateLimiter::for('payments', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Abuse guard on booking creation — generous enough that no
        // legitimate customer or test flow trips it, tight enough to blunt
        // a scripted flood of bookings against providers.
        RateLimiter::for('booking-create', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip());
        });

        // Same rationale as booking-create, kept independent so a burst of
        // proposal submissions can't starve booking creation or vice versa.
        RateLimiter::for('proposal-create', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip());
        });

        // Short, stable aliases for polymorphic *_type columns (payable_type,
        // reviewable_type, disputable_type, attachable_type, auditable_type)
        // instead of raw FQCNs, matching the values documented in the SRS
        // ERD. Strict mode means every model touched by a morph relation
        // must be listed here — that includes User, whose Sanctum tokens()
        // relation is a morphMany, not just the domain models with explicit
        // *_type columns. 'quotation' and 'proposal' only ever appear as
        // AuditLog.auditable_type (neither has another *_type column
        // pointing at it) but AuditLog::auditable() is still a morphTo, so
        // strict mode requires them registered too.
        Relation::enforceMorphMap([
            'user' => User::class,
            'booking' => Booking::class,
            'quotation' => Quotation::class,
            'project' => Project::class,
            'proposal' => Proposal::class,
            'milestone' => Milestone::class,
            'deliverable' => Deliverable::class,
            'dispute' => Dispute::class,
            'payment' => Payment::class,
            'business' => Business::class,
            'contract' => Contract::class,
            'review' => Review::class,
            'payout' => Payout::class,
            'freelancer' => FreelancerProfile::class,
            'platform_setting' => PlatformSetting::class,
            'refund' => Refund::class,
            'failed_login_attempt' => FailedLoginAttempt::class,
        ]);

        // Models live under app/Domain/*/Models (CLAUDE.md §3), but factories
        // stay flat in database/factories/ — Laravel's default guesser mirrors
        // the model's namespace path, which doesn't exist there.
        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Database\\Factories\\'.Str::afterLast($modelName, '\\').'Factory'
        );
    }
}
