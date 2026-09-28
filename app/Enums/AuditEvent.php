<?php

namespace App\Enums;

/**
 * Canonical audit event names.
 *
 * Same reasoning as `PermissionName`: `$audit->record($lease, 'lease.creted')`
 * is a silent no-op in every human-facing report, because the typo simply
 * never matches. Declaring the catalogue means the compiler catches it and
 * `php artisan propertyhub:audit-coverage` can prove every write path is
 * named consistently.
 *
 * CONVENTION: `<resource>.<past-tense-verb>`. The resources used here line up
 * with the permission catalogue, so an event name maps to the permission that
 * permits it — a useful cross-check when reviewing an access log.
 */
enum AuditEvent: string
{
    /* -------------------------------------------------- Authentication */
    case LOGIN_SUCCEEDED = 'auth.login_succeeded';
    case LOGIN_FAILED = 'auth.login_failed';
    case LOGOUT = 'auth.logout';
    case PASSWORD_CHANGED = 'auth.password_changed';
    case PASSWORD_RESET_REQUESTED = 'auth.password_reset_requested';
    case EMAIL_VERIFIED = 'auth.email_verified';

    /* -------------------------------------------------- Tenancy */
    case BUSINESS_CREATED = 'business.created';
    case BUSINESS_UPDATED = 'business.updated';
    case BUSINESS_SWITCHED = 'business.switched';
    case SUBSCRIPTION_CHANGED = 'subscription.changed';
    case SUBSCRIPTION_EXPIRED = 'subscription.expired';

    /* -------------------------------------------------- Staff */
    case STAFF_INVITED = 'staff.invited';
    case STAFF_UPDATED = 'staff.updated';
    case STAFF_ROLE_CHANGED = 'staff.role_changed';
    case STAFF_REMOVED = 'staff.removed';

    /* -------------------------------------------------- Properties */
    case PROPERTY_CREATED = 'property.created';
    case PROPERTY_UPDATED = 'property.updated';
    case PROPERTY_DELETED = 'property.deleted';

    case BUILDING_CREATED = 'building.created';
    case BUILDING_UPDATED = 'building.updated';
    case BUILDING_DELETED = 'building.deleted';

    case UNIT_CREATED = 'unit.created';
    case UNIT_UPDATED = 'unit.updated';
    case UNIT_DELETED = 'unit.deleted';

    /* -------------------------------------------------- Tenants */
    case TENANT_CREATED = 'tenant.created';
    case TENANT_UPDATED = 'tenant.updated';
    case TENANT_DELETED = 'tenant.deleted';

    /* -------------------------------------------------- Leases */
    case LEASE_CREATED = 'lease.created';
    case LEASE_UPDATED = 'lease.updated';
    case LEASE_TERMINATED = 'lease.terminated';
    case LEASE_DELETED = 'lease.deleted';
    case LEASE_EXPIRY_WARNING_SENT = 'lease.expiry_warning_sent';

    /* -------------------------------------------------- Payments */
    case PAYMENT_RECORDED = 'payment.recorded';
    case PAYMENT_UPDATED = 'payment.updated';
    case PAYMENT_DELETED = 'payment.deleted';
    case PAYMENT_RECEIPT_ISSUED = 'payment.receipt_issued';

    /* -------------------------------------------------- Expenses */
    case EXPENSE_CREATED = 'expense.created';
    case EXPENSE_UPDATED = 'expense.updated';
    case EXPENSE_DELETED = 'expense.deleted';

    /* -------------------------------------------------- Maintenance */
    case MAINTENANCE_CREATED = 'maintenance.created';
    case MAINTENANCE_UPDATED = 'maintenance.updated';
    case MAINTENANCE_ASSIGNED = 'maintenance.assigned';
    case MAINTENANCE_STATUS_CHANGED = 'maintenance.status_changed';
    case MAINTENANCE_DELETED = 'maintenance.deleted';

    /* -------------------------------------------------- Documents */
    case DOCUMENT_UPLOADED = 'document.uploaded';
    case DOCUMENT_DOWNLOADED = 'document.downloaded';
    case DOCUMENT_DELETED = 'document.deleted';

    /* -------------------------------------------------- Security */
    case PERMISSION_DENIED = 'security.permission_denied';
    case SUBSCRIPTION_WRITE_BLOCKED = 'security.subscription_write_blocked';
    case SUPER_ADMIN_ACCESSED_TENANT = 'security.super_admin_accessed_tenant';
    case RATE_LIMIT_TRIPPED = 'security.rate_limit_tripped';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The top-level resource segment, used to group the activity feed.
     */
    public function resource(): string
    {
        return explode('.', $this->value)[0];
    }

    public function isSecurityEvent(): bool
    {
        return $this->resource() === 'security' || $this->resource() === 'auth';
    }

    public function label(): string
    {
        return str($this->value)->replace(['.', '_'], ' ')->title()->toString();
    }
}
