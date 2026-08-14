<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\User\Enums\PermissionName;
use App\Domain\User\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds roles and their granular permissions per the SRS §6 permission
 * matrix. Permissions are data (role_has_permissions rows), never a
 * hardcoded role switch — an admin can regrant/revoke without a deploy.
 */
class RoleAndPermissionSeeder extends Seeder
{
    /**
     * @var array<string, list<PermissionName>>
     */
    private const ROLE_PERMISSIONS = [
        RoleName::Customer->value => [
            PermissionName::BookingsCreate,
            PermissionName::BookingsView,
            PermissionName::BookingsCancel,
            PermissionName::BookingsConfirmCompletion,
            PermissionName::QuotationsView,
            PermissionName::QuotationsAccept,
            PermissionName::QuotationsReject,
            PermissionName::ProjectsCreate,
            PermissionName::ProjectsView,
            PermissionName::ProjectsUpdate,
            PermissionName::ProposalsView,
            PermissionName::ProposalsHire,
            PermissionName::ContractsView,
            PermissionName::MilestonesView,
            PermissionName::MilestonesCreate,
            PermissionName::MilestonesApprove,
            PermissionName::MilestonesReject,
            PermissionName::ReviewsCreate,
            PermissionName::ReviewsView,
            PermissionName::BusinessesView,
            PermissionName::ServicesView,
            PermissionName::DisputesCreate,
            PermissionName::DisputesView,
            PermissionName::PaymentsCreate,
            PermissionName::PaymentsView,
        ],

        // Owner: everything a staff member can do, plus business-level
        // administration (profile/settings, payouts).
        RoleName::ProviderOwner->value => [
            PermissionName::BookingsView,
            PermissionName::BookingsCancel,
            PermissionName::BookingsManageStatus,
            PermissionName::QuotationsCreate,
            PermissionName::QuotationsRevise,
            PermissionName::QuotationsView,
            PermissionName::BusinessesView,
            PermissionName::BusinessesManage,
            PermissionName::ServicesView,
            PermissionName::ServicesManage,
            PermissionName::ProjectsView,
            PermissionName::ReviewsView,
            PermissionName::ReviewsReply,
            PermissionName::DisputesCreate,
            PermissionName::DisputesView,
            PermissionName::PaymentsView,
            PermissionName::AuditLogsViewOwn,
        ],

        // Staff: day-to-day booking/quotation/service operations, but
        // cannot change business settings or view payouts — no staff
        // membership model exists yet, so this role has no assignment
        // path in the app today; it is seeded ahead of that feature.
        RoleName::ProviderStaff->value => [
            PermissionName::BookingsView,
            PermissionName::BookingsCancel,
            PermissionName::BookingsManageStatus,
            PermissionName::QuotationsCreate,
            PermissionName::QuotationsRevise,
            PermissionName::QuotationsView,
            PermissionName::BusinessesView,
            PermissionName::ServicesView,
            PermissionName::ServicesManage,
            PermissionName::ProjectsView,
            PermissionName::ReviewsView,
            PermissionName::ReviewsReply,
            PermissionName::DisputesCreate,
            PermissionName::DisputesView,
            PermissionName::PaymentsView,
            PermissionName::AuditLogsViewOwn,
        ],

        RoleName::Freelancer->value => [
            PermissionName::ProjectsView,
            PermissionName::ServicesView,
            PermissionName::ProposalsCreate,
            PermissionName::ProposalsView,
            PermissionName::ContractsView,
            PermissionName::MilestonesView,
            PermissionName::MilestonesSubmit,
            PermissionName::ReviewsView,
            PermissionName::DisputesCreate,
            PermissionName::DisputesView,
            PermissionName::PaymentsView,
            PermissionName::AuditLogsViewOwn,
        ],

        // Admin: oversight, approval, and moderation — never the
        // participant-side create actions (booking, quotation, proposal…)
        // those stay exclusive to the role that owns the transaction.
        RoleName::Admin->value => [
            PermissionName::BookingsView,
            PermissionName::QuotationsView,
            PermissionName::ProjectsView,
            PermissionName::ProposalsView,
            PermissionName::ContractsView,
            PermissionName::MilestonesView,
            PermissionName::ReviewsView,
            PermissionName::BusinessesView,
            PermissionName::BusinessesApprove,
            PermissionName::ServicesView,
            PermissionName::FreelancersApprove,
            PermissionName::DisputesView,
            PermissionName::DisputesResolve,
            PermissionName::PaymentsView,
            PermissionName::PaymentsRefund,
            PermissionName::AuditLogsView,
            PermissionName::CategoriesManage,
            PermissionName::PlatformSettingsManage,
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        // syncPermissions() below resolves names against the registrar's
        // cached permission collection, which the loop above just made
        // stale — Permission::findOrCreate() doesn't self-invalidate it.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');

            $permissions = match ($roleName) {
                RoleName::SuperAdmin => PermissionName::cases(),
                default => self::ROLE_PERMISSIONS[$roleName->value] ?? [],
            };

            $role->syncPermissions(array_map(fn (PermissionName $p) => $p->value, $permissions));
        }
    }
}
