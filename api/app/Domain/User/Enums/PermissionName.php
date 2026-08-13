<?php

declare(strict_types=1);

namespace App\Domain\User\Enums;

enum PermissionName: string
{
    case BookingsCreate = 'bookings.create';
    case BookingsView = 'bookings.view';
    case BookingsCancel = 'bookings.cancel';

    case QuotationsCreate = 'quotations.create';
    case QuotationsRevise = 'quotations.revise';
    case QuotationsAccept = 'quotations.accept';
    case QuotationsReject = 'quotations.reject';
    case QuotationsView = 'quotations.view';

    case ProjectsCreate = 'projects.create';
    case ProjectsView = 'projects.view';
    case ProjectsUpdate = 'projects.update';

    case ProposalsCreate = 'proposals.create';
    case ProposalsView = 'proposals.view';
    case ProposalsHire = 'proposals.hire';

    case ContractsView = 'contracts.view';

    case MilestonesView = 'milestones.view';
    case MilestonesSubmit = 'milestones.submit';
    case MilestonesApprove = 'milestones.approve';

    case ReviewsCreate = 'reviews.create';
    case ReviewsReply = 'reviews.reply';
    case ReviewsView = 'reviews.view';

    case BusinessesView = 'businesses.view';
    case BusinessesManage = 'businesses.manage';
    case BusinessesApprove = 'businesses.approve';

    case ServicesView = 'services.view';
    case ServicesManage = 'services.manage';

    case FreelancersApprove = 'freelancers.approve';

    case DisputesCreate = 'disputes.create';
    case DisputesView = 'disputes.view';
    case DisputesResolve = 'disputes.resolve';

    case AuditLogsView = 'audit-logs.view';
    case AuditLogsViewOwn = 'audit-logs.view-own';

    case PaymentsCreate = 'payments.create';
    case PaymentsView = 'payments.view';
    case PaymentsRefund = 'payments.refund';

    case CategoriesManage = 'categories.manage';
    case PlatformSettingsManage = 'platform-settings.manage';

    case AdminsManage = 'admins.manage';
}
