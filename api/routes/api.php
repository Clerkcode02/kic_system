<?php

declare(strict_types=1);

use App\Domain\Payment\Webhooks\StripeWebhookController;
use App\Http\Controllers\Api\V1\Admin\Catalog\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\Payment\RefundPaymentController;
use App\Http\Controllers\Api\V1\Admin\Platform\PlatformSettingController;
use App\Http\Controllers\Api\V1\Customer\Booking\ConfirmBookingCompletionController;
use App\Http\Controllers\Api\V1\Customer\Booking\StoreBookingController;
use App\Http\Controllers\Api\V1\Customer\Project\CancelProjectController;
use App\Http\Controllers\Api\V1\Customer\Project\StoreProjectController;
use App\Http\Controllers\Api\V1\Customer\Project\UpdateProjectController;
use App\Http\Controllers\Api\V1\Customer\Proposal\HireProposalController;
use App\Http\Controllers\Api\V1\Customer\Proposal\ShortlistProposalController;
use App\Http\Controllers\Api\V1\Customer\Quotation\AcceptQuotationController;
use App\Http\Controllers\Api\V1\Customer\Quotation\RejectQuotationController;
use App\Http\Controllers\Api\V1\Freelancer\Proposal\ListMyProposalsController;
use App\Http\Controllers\Api\V1\Freelancer\Proposal\StoreProposalController;
use App\Http\Controllers\Api\V1\Freelancer\Proposal\WithdrawProposalController;
use App\Http\Controllers\Api\V1\Provider\Booking\CheckInBookingController;
use App\Http\Controllers\Api\V1\Provider\Booking\CompleteBookingController;
use App\Http\Controllers\Api\V1\Provider\Business\AvailabilityController as ProviderAvailabilityManagementController;
use App\Http\Controllers\Api\V1\Provider\Business\BusinessDocumentController;
use App\Http\Controllers\Api\V1\Provider\Business\BusinessProfileController;
use App\Http\Controllers\Api\V1\Provider\Business\EarningsController;
use App\Http\Controllers\Api\V1\Provider\Business\StripeConnectController;
use App\Http\Controllers\Api\V1\Provider\Business\SubmitForVerificationController;
use App\Http\Controllers\Api\V1\Provider\Catalog\ServiceController as ProviderServiceController;
use App\Http\Controllers\Api\V1\Provider\Quotation\ReviseQuotationController;
use App\Http\Controllers\Api\V1\Provider\Quotation\StoreQuotationController as StoreQuotationForBookingController;
use App\Http\Controllers\Api\V1\Shared\Attachment\AttachmentController;
use App\Http\Controllers\Api\V1\Shared\Audit\AuditLogController;
use App\Http\Controllers\Api\V1\Shared\Auth\LoginController;
use App\Http\Controllers\Api\V1\Shared\Auth\LogoutAllDevicesController;
use App\Http\Controllers\Api\V1\Shared\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Shared\Auth\MeController;
use App\Http\Controllers\Api\V1\Shared\Auth\RegisterBusinessController;
use App\Http\Controllers\Api\V1\Shared\Auth\RegisterCustomerController;
use App\Http\Controllers\Api\V1\Shared\Auth\RegisterFreelancerController;
use App\Http\Controllers\Api\V1\Shared\Auth\ResendEmailVerificationController;
use App\Http\Controllers\Api\V1\Shared\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\Shared\Booking\BookingController;
use App\Http\Controllers\Api\V1\Shared\Booking\CancelBookingController;
use App\Http\Controllers\Api\V1\Shared\Business\ProviderAvailabilityController;
use App\Http\Controllers\Api\V1\Shared\Catalog\CategoryController;
use App\Http\Controllers\Api\V1\Shared\Catalog\ServiceController;
use App\Http\Controllers\Api\V1\Shared\Contract\ContractController;
use App\Http\Controllers\Api\V1\Shared\Contract\StoreContractMilestonesController;
use App\Http\Controllers\Api\V1\Shared\Dispute\DisputeController;
use App\Http\Controllers\Api\V1\Shared\Milestone\ApproveMilestoneController;
use App\Http\Controllers\Api\V1\Shared\Milestone\ConfirmDeliverableController;
use App\Http\Controllers\Api\V1\Shared\Milestone\MilestoneDeliverableController;
use App\Http\Controllers\Api\V1\Shared\Milestone\RejectMilestoneController;
use App\Http\Controllers\Api\V1\Shared\Milestone\RequestDeliverableUploadUrlController;
use App\Http\Controllers\Api\V1\Shared\Milestone\SubmitMilestoneController;
use App\Http\Controllers\Api\V1\Shared\Notification\NotificationController;
use App\Http\Controllers\Api\V1\Shared\Notification\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Shared\Payment\CreatePaymentIntentController;
use App\Http\Controllers\Api\V1\Shared\Project\ProjectController;
use App\Http\Controllers\Api\V1\Shared\Proposal\ProjectProposalController;
use App\Http\Controllers\Api\V1\Shared\Quotation\QuotationController;
use App\Http\Controllers\Api\V1\Shared\Review\BookingReviewController;
use App\Http\Controllers\Api\V1\Shared\Review\BusinessReviewController;
use App\Http\Controllers\Api\V1\Shared\Review\ProjectReviewController;
use App\Http\Controllers\Api\V1\Shared\Review\ReplyToReviewController;
use App\Http\Controllers\Api\V1\Shared\Upload\UploadController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->name('auth.')->middleware('throttle:auth')->group(function () {
    Route::post('register/customer', RegisterCustomerController::class)->name('register.customer');
    Route::post('register/business', RegisterBusinessController::class)->name('register.business');
    Route::post('register/freelancer', RegisterFreelancerController::class)->name('register.freelancer');

    Route::post('login', LoginController::class)->name('login');

    Route::post('email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('email/resend', ResendEmailVerificationController::class)->name('verification.resend');

    Route::middleware(['auth:sanctum', 'not-suspended'])->group(function () {
        Route::get('me', MeController::class)->name('me');
        Route::post('logout', LogoutController::class)->name('logout');
        Route::post('logout-all-devices', LogoutAllDevicesController::class)->name('logout-all-devices');
    });
});

Route::prefix('v1')->name('catalog.')->group(function () {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show');
    Route::get('services/{service}/pricing', [ServiceController::class, 'pricing'])->name('services.pricing');

    Route::get('providers/{business}/availability', [ProviderAvailabilityController::class, 'show'])->name('providers.availability');

    Route::get('businesses/{business}/reviews', BusinessReviewController::class)->name('businesses.reviews.index');
});

Route::prefix('v1/bookings')->name('bookings.')->middleware('api.protected')->group(function () {
    Route::get('/', [BookingController::class, 'index'])->name('index');
    Route::get('{booking}', [BookingController::class, 'show'])->name('show');
    Route::post('/', StoreBookingController::class)->middleware('idempotent')->name('store');
    Route::patch('{booking}/cancel', CancelBookingController::class)->name('cancel');
    Route::post('{booking}/check-in', CheckInBookingController::class)->name('check-in');
    Route::post('{booking}/complete', CompleteBookingController::class)->name('complete');
    Route::post('{booking}/confirm-completion', ConfirmBookingCompletionController::class)->name('confirm-completion');
    Route::post('{booking}/quotations', StoreQuotationForBookingController::class)->name('quotations.store');
    Route::post('{booking}/reviews', BookingReviewController::class)->name('reviews.store');
});

Route::prefix('v1/reviews')->name('reviews.')->middleware('api.protected')->group(function () {
    Route::post('{review}/reply', ReplyToReviewController::class)->name('reply');
});

Route::prefix('v1/quotations')->name('quotations.')->middleware('api.protected')->group(function () {
    Route::get('{quotation}', [QuotationController::class, 'show'])->name('show');
    Route::post('{quotation}/accept', AcceptQuotationController::class)->middleware('idempotent')->name('accept');
    Route::post('{quotation}/reject', RejectQuotationController::class)->name('reject');
    Route::post('{quotation}/revise', ReviseQuotationController::class)->name('revise');
});

Route::prefix('v1/projects')->name('projects.')->group(function () {
    // Public browsing, matching services/categories (CLAUDE.md §4 — browse
    // is ✅ for every role, including anonymous visitors).
    Route::get('/', [ProjectController::class, 'index'])->name('index');
    Route::get('{project}', [ProjectController::class, 'show'])->name('show');

    Route::middleware('api.protected')->group(function () {
        Route::post('/', StoreProjectController::class)->name('store');
        Route::patch('{project}', UpdateProjectController::class)->name('update');
        Route::delete('{project}', CancelProjectController::class)->name('destroy');

        Route::post('{project}/proposals', StoreProposalController::class)->name('proposals.store');
        Route::get('{project}/proposals', ProjectProposalController::class)->name('proposals.index');
        Route::post('{project}/reviews', ProjectReviewController::class)->name('reviews.store');
    });
});

Route::prefix('v1/proposals')->name('proposals.')->middleware('api.protected')->group(function () {
    Route::post('{proposal}/shortlist', ShortlistProposalController::class)->name('shortlist');
    Route::post('{proposal}/withdraw', WithdrawProposalController::class)->name('withdraw');
    Route::post('{proposal}/hire', HireProposalController::class)->name('hire');
});

Route::prefix('v1/freelancers/me')->name('freelancers.me.')->middleware(['api.protected', 'role:freelancer'])->group(function () {
    Route::get('proposals', ListMyProposalsController::class)->name('proposals');
});

Route::prefix('v1/contracts')->name('contracts.')->middleware('api.protected')->group(function () {
    Route::get('{contract}', [ContractController::class, 'show'])->name('show');
    Route::post('{contract}/milestones', StoreContractMilestonesController::class)->name('milestones.store');
});

Route::prefix('v1/milestones')->name('milestones.')->middleware('api.protected')->group(function () {
    Route::post('{milestone}/submit', SubmitMilestoneController::class)->name('submit');
    Route::post('{milestone}/approve', ApproveMilestoneController::class)->name('approve');
    Route::post('{milestone}/reject', RejectMilestoneController::class)->name('reject');
    Route::get('{milestone}/deliverables', [MilestoneDeliverableController::class, 'index'])->name('deliverables.index');
    Route::post('{milestone}/deliverables/upload-url', RequestDeliverableUploadUrlController::class)->name('deliverables.upload-url');
    Route::post('{milestone}/deliverables', ConfirmDeliverableController::class)->name('deliverables.store');
});

Route::prefix('v1/me/notification-preferences')->name('notification-preferences.')->middleware('api.protected')->group(function () {
    Route::get('/', [NotificationPreferenceController::class, 'show'])->name('show');
    Route::patch('/', [NotificationPreferenceController::class, 'update'])->name('update');
});

Route::prefix('v1/notifications')->name('notifications.')->middleware('api.protected')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::post('{notification}/read', [NotificationController::class, 'read'])->name('read');
    Route::post('read-all', [NotificationController::class, 'readAll'])->name('read-all');
});

Route::prefix('v1/payments')->name('payments.')->middleware('api.protected')->group(function () {
    Route::post('intents', CreatePaymentIntentController::class)->middleware('idempotent')->name('intents.store');
});

Route::prefix('v1/audit-logs')->name('audit-logs.')->middleware('api.protected')->group(function () {
    Route::get('/', [AuditLogController::class, 'index'])->name('index');
});

Route::prefix('v1/disputes')->name('disputes.')->middleware('api.protected')->group(function () {
    Route::get('/', [DisputeController::class, 'index'])->name('index');
    Route::post('/', [DisputeController::class, 'store'])->name('store');
    Route::get('{dispute}', [DisputeController::class, 'show'])->name('show');
    Route::post('{dispute}/resolve', [DisputeController::class, 'resolve'])->name('resolve');
});

Route::prefix('v1/uploads')->name('uploads.')->middleware('api.protected')->group(function () {
    Route::post('presign', [UploadController::class, 'presign'])->name('presign');
    Route::post('confirm', [UploadController::class, 'confirm'])->name('confirm');
});

Route::prefix('v1/attachments')->name('attachments.')->middleware('api.protected')->group(function () {
    Route::get('{attachment}/url', [AttachmentController::class, 'url'])->name('url');
});

// Public, unauthenticated, signature-verified — no api.protected/idempotent
// middleware; Stripe (not a logged-in user) calls this (CLAUDE.md §7/§14).
Route::post('v1/webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');

Route::prefix('v1/admin')->name('admin.')->middleware(['api.protected', 'role:admin,super_admin'])->group(function () {
    Route::post('categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::patch('categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('categories/reorder', [AdminCategoryController::class, 'reorder'])->name('categories.reorder');

    Route::post('payments/{payment}/refund', RefundPaymentController::class)->middleware('idempotent')->name('payments.refund');

    Route::get('platform-settings', [PlatformSettingController::class, 'index'])->name('platform-settings.index');
    Route::patch('platform-settings/{key}', [PlatformSettingController::class, 'update'])->name('platform-settings.update');
});

Route::prefix('v1/provider')->name('provider.')->middleware(['api.protected', 'role:provider_owner,provider_staff'])->group(function () {
    Route::get('me', [BusinessProfileController::class, 'show'])->name('me.show');
    Route::patch('me', [BusinessProfileController::class, 'update'])->name('me.update');

    Route::post('me/documents/upload-url', [BusinessDocumentController::class, 'uploadUrl'])->name('me.documents.upload-url');
    Route::post('me/documents', [BusinessDocumentController::class, 'store'])->name('me.documents.store');

    Route::post('me/submit-for-verification', SubmitForVerificationController::class)->name('me.submit-for-verification');

    Route::get('me/services', [ProviderServiceController::class, 'index'])->name('services.index');
    Route::post('services', [ProviderServiceController::class, 'store'])->name('services.store');
    Route::get('services/{service}', [ProviderServiceController::class, 'show'])->name('services.show');
    Route::patch('services/{service}', [ProviderServiceController::class, 'update'])->name('services.update');
    Route::delete('services/{service}', [ProviderServiceController::class, 'destroy'])->name('services.destroy');

    Route::get('me/availability', [ProviderAvailabilityManagementController::class, 'show'])->name('me.availability.show');
    Route::put('me/availability', [ProviderAvailabilityManagementController::class, 'update'])->name('me.availability.update');

    Route::post('me/stripe/onboarding-link', [StripeConnectController::class, 'onboardingLink'])->name('me.stripe.onboarding-link');
    Route::get('me/stripe/status', [StripeConnectController::class, 'status'])->name('me.stripe.status');

    Route::get('me/earnings', [EarningsController::class, 'index'])->name('me.earnings');
});
