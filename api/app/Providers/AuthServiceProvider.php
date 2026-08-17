<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Service;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\Payment\Models\Payment;
use App\Domain\Platform\Models\PlatformSetting;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\Review\Models\Review;
use App\Policies\AuditLogPolicy;
use App\Policies\BookingPolicy;
use App\Policies\BusinessPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\ContractPolicy;
use App\Policies\DisputePolicy;
use App\Policies\MilestonePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PlatformSettingPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ProposalPolicy;
use App\Policies\QuotationPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\ServicePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Notifications\DatabaseNotification;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Booking::class => BookingPolicy::class,
        Quotation::class => QuotationPolicy::class,
        Project::class => ProjectPolicy::class,
        Proposal::class => ProposalPolicy::class,
        Contract::class => ContractPolicy::class,
        Milestone::class => MilestonePolicy::class,
        Review::class => ReviewPolicy::class,
        Business::class => BusinessPolicy::class,
        Category::class => CategoryPolicy::class,
        Service::class => ServicePolicy::class,
        Dispute::class => DisputePolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        Payment::class => PaymentPolicy::class,
        PlatformSetting::class => PlatformSettingPolicy::class,
        DatabaseNotification::class => NotificationPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
