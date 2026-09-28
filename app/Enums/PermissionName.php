<?php

namespace App\Enums;

/**
 * The complete catalogue of abilities in PropertyHub.
 *
 * WHY AN ENUM RATHER THAN AD-HOC STRINGS
 * -------------------------------------
 * `$user->can('properties.delete')` compiles to a plain string at the call
 * site, so a typo silently evaluates to "denied" and nobody finds out until
 * a user reports a missing button. Declaring the catalogue as a backed enum
 * means the compiler rejects `can(PermissionName::propertis->delete)` and the
 * seeder is the single source of truth for what exists in the database.
 *
 * NAMING CONVENTION: `<resource>.<action>`.
 *   - `view`   is deliberately read-only and never implies a write.
 *   - Actions are listed most-privileged last so that a role can be granted
 *     a contiguous slice of a resource's cases.
 *
 * These values are written to the `permissions` table by RolePermissionSeeder
 * and read back by spatie, so the backing strings are a data contract.
 */
enum PermissionName: string
{
    /* -------------------------------------------------- Properties */
    case PROPERTIES_VIEW = 'properties.view';
    case PROPERTIES_CREATE = 'properties.create';
    case PROPERTIES_UPDATE = 'properties.update';
    case PROPERTIES_DELETE = 'properties.delete';

    /* -------------------------------------------------- Buildings */
    case BUILDINGS_VIEW = 'buildings.view';
    case BUILDINGS_CREATE = 'buildings.create';
    case BUILDINGS_UPDATE = 'buildings.update';
    case BUILDINGS_DELETE = 'buildings.delete';

    /* -------------------------------------------------- Units */
    case UNITS_VIEW = 'units.view';
    case UNITS_CREATE = 'units.create';
    case UNITS_UPDATE = 'units.update';
    case UNITS_DELETE = 'units.delete';

    /* -------------------------------------------------- Tenants */
    case TENANTS_VIEW = 'tenants.view';
    case TENANTS_CREATE = 'tenants.create';
    case TENANTS_UPDATE = 'tenants.update';
    case TENANTS_DELETE = 'tenants.delete';

    /* -------------------------------------------------- Leases */
    case LEASES_VIEW = 'leases.view';
    case LEASES_CREATE = 'leases.create';
    case LEASES_UPDATE = 'leases.update';
    case LEASES_DELETE = 'leases.delete';

    /* -------------------------------------------------- Rent payments */
    case PAYMENTS_VIEW = 'payments.view';
    case PAYMENTS_RECORD = 'payments.record';
    case PAYMENTS_RECEIPT = 'payments.receipt';
    case PAYMENTS_DELETE = 'payments.delete';

    /* -------------------------------------------------- Expenses */
    case EXPENSES_VIEW = 'expenses.view';
    case EXPENSES_CREATE = 'expenses.create';
    case EXPENSES_UPDATE = 'expenses.update';
    case EXPENSES_DELETE = 'expenses.delete';

    /* -------------------------------------------------- Maintenance */
    case MAINTENANCE_VIEW = 'maintenance.view';

    /**
     * Read access limited to requests assigned to the signed-in staff
     * member. Maintenance staff hold ONLY this — never MAINTENANCE_VIEW,
     * which would expose every tenant's request history.
     */
    case MAINTENANCE_VIEW_ASSIGNED = 'maintenance.view_assigned';
    case MAINTENANCE_CREATE = 'maintenance.create';
    case MAINTENANCE_UPDATE_STATUS = 'maintenance.update_status';
    case MAINTENANCE_ASSIGN = 'maintenance.assign';
    case MAINTENANCE_VIEW_COSTS = 'maintenance.view_costs';
    case MAINTENANCE_DELETE = 'maintenance.delete';

    /* -------------------------------------------------- Documents */
    case DOCUMENTS_VIEW = 'documents.view';
    case DOCUMENTS_UPLOAD = 'documents.upload';
    case DOCUMENTS_DOWNLOAD = 'documents.download';
    case DOCUMENTS_DELETE = 'documents.delete';

    /* -------------------------------------------------- Staff */
    case STAFF_VIEW = 'staff.view';
    case STAFF_INVITE = 'staff.invite';
    case STAFF_UPDATE = 'staff.update';
    case STAFF_REMOVE = 'staff.remove';

    /**
     * Assign the owner role. Held only by existing owners, and checked before
     * any privilege-escalation path is offered.
     */
    case STAFF_GRANT_OWNER = 'staff.grant_owner';
    case STAFF_ASSIGN_ROLE = 'staff.assign_role';

    /* -------------------------------------------------- Reports */
    case REPORTS_VIEW = 'reports.view';
    case REPORTS_FINANCIAL = 'reports.financial';
    case REPORTS_OCCUPANCY = 'reports.occupancy';
    case REPORTS_EXPORT = 'reports.export';

    /* -------------------------------------------------- Renewal Notices */
    case RENEWAL_NOTICES_VIEW = 'renewal_notices.view';
    case RENEWAL_NOTICES_CREATE = 'renewal_notices.create';
    case RENEWAL_NOTICES_UPDATE = 'renewal_notices.update';
    case RENEWAL_NOTICES_DELETE = 'renewal_notices.delete';

    /* -------------------------------------------------- Journal */
    case JOURNALS_VIEW = 'journals.view';
    case JOURNALS_CREATE = 'journals.create';
    case JOURNALS_UPDATE = 'journals.update';
    case JOURNALS_DELETE = 'journals.delete';

    /* -------------------------------------------------- Messages */
    case MESSAGES_VIEW = 'messages.view';
    case MESSAGES_SEND = 'messages.send';
    case MESSAGES_REPLY = 'messages.reply';
    case MESSAGES_DELETE = 'messages.delete';

    /* -------------------------------------------------- Threading & attachments */
    case MESSAGES_THREAD = 'messages.thread';
    case MESSAGES_ATTACH = 'messages.attach';
    case MESSAGES_NOTIFY = 'messages.notify';

    /* -------------------------------------------------- Audit */
    case AUDIT_VIEW = 'audit.view';

    /* -------------------------------------------------- Analytics */
    case ANALYTICS_VIEW = 'analytics.view';

    /* -------------------------------------------------- Settings & billing */
    case SETTINGS_MANAGE = 'settings.manage';
    case SUBSCRIPTION_VIEW = 'subscription.view';
    case SUBSCRIPTION_MANAGE = 'subscription.manage';

    /* -------------------------------------------------- Tenant portal */
    case PORTAL_VIEW_LEASE = 'portal.view_lease';
    case PORTAL_VIEW_PAYMENTS = 'portal.view_payments';
    case PORTAL_VIEW_DOCUMENTS = 'portal.view_documents';
    case PORTAL_SUBMIT_MAINTENANCE = 'portal.submit_maintenance';

    /**
     * Every permission, in declaration order.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Grouped by resource for the role editor UI.
     *
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
            $resource = explode('.', $permission->value)[0];
            $groups[$resource][] = $permission;
        }

        return $groups;
    }

    /**
     * Prettified label, e.g. `payments.record` -> "Record payment".
     */
    public function label(): string
    {
        [$resource, $action] = explode('.', $this->value);

        $resourceLabel = str($resource)->replace('_', ' ')->title()->toString();

        $actionLabel = match ($action) {
            'view' => 'View',
            'view_assigned' => 'View assigned',
            'view_costs' => 'View costs',
            'create' => 'Create',
            'update' => 'Update',
            'update_status' => 'Update status',
            'assign' => 'Assign',
            'record' => 'Record',
            'receipt' => 'Issue receipt',
            'delete' => 'Delete',
            'export' => 'Export',
            'download' => 'Download',
            'upload' => 'Upload',
            'invite' => 'Invite',
            'remove' => 'Remove',
            'grant_owner' => 'Grant owner',
            'manage' => 'Manage',
            'submit_maintenance' => 'Submit maintenance',
            default => str($action)->replace('_', ' ')->title()->toString(),
        };

        return "{$actionLabel} {$resourceLabel}";
    }

    /**
     * Is this permission a write? Drives the "you are about to change data"
     * warning and lets the UI distinguish read-only from edit access.
     */
    public function isWrite(): bool
    {
        return ! in_array(explode('.', $this->value)[1], [
            'view', 'view_assigned', 'view_costs', 'receipt', 'export', 'download',
        ], strict: true);
    }
}
