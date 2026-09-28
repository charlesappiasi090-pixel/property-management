<?php

namespace App\Enums;

/**
 * The five job functions a person can hold inside a business.
 *
 * Roles are PER BUSINESS. A user who is an `owner` of one landlord's
 * portfolio is only a `property_manager` for another; that scoping is
 * enforced by spatie/laravel-permission's teams feature (see
 * config/permission.php -> teams / team_foreign_key).
 *
 * The backing values are the strings stored in the `roles` table, so the
 * enum cases must never be renamed once real data exists — add a new case
 * and migrate instead.
 *
 * This enum is also the single source of truth for the role -> permission
 * matrix (`permissionsFor()`). Both the seeder and
 * `BusinessMembershipService::ensureRoleExists()` read it, so a business
 * created by a user signing up gets exactly the same grants as one created
 * by a seeder, and a permission added in a later release reaches both.
 */
enum Role: string
{
    case OWNER = 'owner';
    case PROPERTY_MANAGER = 'property_manager';
    case ACCOUNTANT = 'accountant';
    case MAINTENANCE_STAFF = 'maintenance_staff';
    case TENANT = 'tenant';

    /**
     * Human label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::PROPERTY_MANAGER => 'Property Manager',
            self::ACCOUNTANT => 'Accountant',
            self::MAINTENANCE_STAFF => 'Maintenance Staff',
            self::TENANT => 'Tenant',
        };
    }

    /**
     * Description shown on the staff-invitation screen.
     */
    public function description(): string
    {
        return match ($this) {
            self::OWNER => 'Unrestricted access to every part of the business, including billing and staff management.',
            self::PROPERTY_MANAGER => 'Manages properties, units, tenants, leases and maintenance requests.',
            self::ACCOUNTANT => 'Handles rent payments, expenses and financial reports. No access to tenant personal data.',
            self::MAINTENANCE_STAFF => 'Sees only the maintenance requests assigned to them, plus the contact details needed to complete the job.',
            self::TENANT => 'Portal access to their own lease, rent, receipts, documents and maintenance requests.',
        };
    }

    /**
     * Roles that operate INSIDE the back-office dashboard.
     *
     * The tenant role is deliberately excluded: a tenant is routed to the
     * separate tenant portal (Phase 14) and must never see back-office
     * navigation, even though the account authenticates with the same guard.
     */
    public function usesBackOffice(): bool
    {
        return $this !== self::TENANT;
    }

    /**
     * Roles a business owner may assign to another member.
     *
     * An owner cannot hand out `owner` casually — that would allow privilege
     * escalation through the invitation screen — nor `tenant`, because a
     * tenant is provisioned from a lease rather than invited as staff.
     * Assigning an owner is a dedicated flow gated on `staff.grant_owner`.
     */
    public function isAssignableByOwner(): bool
    {
        return in_array($this, [
            self::PROPERTY_MANAGER,
            self::ACCOUNTANT,
            self::MAINTENANCE_STAFF,
        ], strict: true);
    }

    /**
     * THE ROLE -> PERMISSION MATRIX.
     *
     * This is the documentation of the product's access model. Every grant
     * below is a deliberate decision about a role's blast radius, and the
     * `// why` comments exist so a future change has to confront the
     * reasoning rather than guess.
     *
     * Row-level scoping (a tenant seeing only their own lease, maintenance
     * staff seeing only their assigned jobs) is NOT expressed here. That is
     * the job of the policies; this map only answers "may this person reach
     * this area of the product at all".
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        $p = PermissionName::class;

        return match ($this) {
            self::OWNER => PermissionName::values(),

            self::PROPERTY_MANAGER => [
                // Owns the physical portfolio day to day.
                $p::PROPERTIES_VIEW, $p::PROPERTIES_CREATE, $p::PROPERTIES_UPDATE, $p::PROPERTIES_DELETE,
                $p::BUILDINGS_VIEW, $p::BUILDINGS_CREATE, $p::BUILDINGS_UPDATE, $p::BUILDINGS_DELETE,
                $p::UNITS_VIEW, $p::UNITS_CREATE, $p::UNITS_UPDATE, $p::UNITS_DELETE,

                // Tenants and leases are the property manager's core work.
                $p::TENANTS_VIEW, $p::TENANTS_CREATE, $p::TENANTS_UPDATE, $p::TENANTS_DELETE,
                $p::LEASES_VIEW, $p::LEASES_CREATE, $p::LEASES_UPDATE, $p::LEASES_DELETE,

                // Records rent as part of the day job, so they can create
                // payments and issue receipts — but NOT delete a payment.
                // Reversing a financial record is an accountant's action: it
                // must leave an auditable trail, not silently vanish.
                $p::PAYMENTS_VIEW, $p::PAYMENTS_RECORD, $p::PAYMENTS_RECEIPT,

                // Maintenance is theirs to run: triage, assign, close, price.
                $p::MAINTENANCE_VIEW, $p::MAINTENANCE_CREATE, $p::MAINTENANCE_UPDATE_STATUS,
                $p::MAINTENANCE_ASSIGN, $p::MAINTENANCE_VIEW_COSTS, $p::MAINTENANCE_DELETE,

                // Documents and reports they need to do the job.
                $p::DOCUMENTS_VIEW, $p::DOCUMENTS_UPLOAD, $p::DOCUMENTS_DOWNLOAD,
                $p::REPORTS_VIEW, $p::REPORTS_OCCUPANCY,

                // Read-only visibility of the team, so they can see who else
                // is on a case — but they cannot invite or remove staff.
                $p::STAFF_VIEW,
            ],

            self::ACCOUNTANT => [
                // Needs portfolio context to reconcile money, but cannot
                // change the physical estate: view-only here.
                $p::PROPERTIES_VIEW, $p::BUILDINGS_VIEW, $p::UNITS_VIEW, $p::LEASES_VIEW,

                // Tenants are viewable so a payment can be matched to a payer
                // and arrears chased. NO create/update/delete: an accountant
                // has no business editing a tenant's identity documents.
                $p::TENANTS_VIEW,

                // Full control of the money, including deletion of a
                // mis-keyed payment. This is the one place a delete grant is
                // deliberate, and every such action is audit-logged.
                $p::PAYMENTS_VIEW, $p::PAYMENTS_RECORD, $p::PAYMENTS_RECEIPT, $p::PAYMENTS_DELETE,
                $p::EXPENSES_VIEW, $p::EXPENSES_CREATE, $p::EXPENSES_UPDATE, $p::EXPENSES_DELETE,

                $p::DOCUMENTS_VIEW, $p::DOCUMENTS_UPLOAD, $p::DOCUMENTS_DOWNLOAD,
                $p::MAINTENANCE_VIEW, $p::MAINTENANCE_VIEW_COSTS,

                // Reports are the reason this role exists.
                $p::REPORTS_VIEW, $p::REPORTS_FINANCIAL, $p::REPORTS_OCCUPANCY, $p::REPORTS_EXPORT,
            ],

            self::MAINTENANCE_STAFF => [
                // Read access to ONLY the requests assigned to them.
                //
                // Deliberately NOT `maintenance.view`: that permission lists
                // every request in the business, which would expose other
                // tenants' names, unit numbers and descriptions. The
                // row-level filtering for this role lives in
                // App\Policies\MaintenanceRequestPolicy.
                $p::MAINTENANCE_VIEW_ASSIGNED,
            ],

            self::TENANT => [
                // Portal access is scoped by policy to the tenant's OWN lease,
                // payments and documents. These four permissions only turn the
                // portal on; the "own" filtering is row-level.
                $p::PORTAL_VIEW_LEASE,
                $p::PORTAL_VIEW_PAYMENTS,
                $p::PORTAL_VIEW_DOCUMENTS,
                $p::PORTAL_SUBMIT_MAINTENANCE,
            ],
        };
    }

    /**
     * @return list<self>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): self => $role, self::cases());
    }
}
