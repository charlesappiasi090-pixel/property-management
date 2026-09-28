<?php

namespace App\Enums;

/**
 * Lifecycle of a tenant business account.
 *
 * The value stored in `businesses.status`. This is a denormalised label: the
 * authoritative entitlement signal is `subscriptions.status`, and
 * `App\Services\Billing\SubscriptionState` reconciles the two. Keeping the
 * column means the request gate can make a decision without joining the
 * subscriptions table on every request.
 */
enum BusinessStatus: string
{
    /** Signed up, inside the plan's trial window. Fully functional. */
    case TRIALING = 'trialing';

    /** Paid (or manually granted) and inside the current period. */
    case ACTIVE = 'active';

    /**
     * Billing failed but the grace period has not elapsed. Read-only, with a
     * persistent banner prompting the update of the payment method.
     */
    case PAST_DUE = 'past_due';

    /** Cancelled; still usable until `current_period_end`. */
    case CANCELED = 'canceled';

    /** No active subscription and no grace period. Read-only, upgrade required. */
    case EXPIRED = 'expired';

    /** Administratively suspended (abuse, manual review). All access blocked. */
    case SUSPENDED = 'suspended';

    /**
     * Business is read-only: the user can sign in and read data, but every
     * write is rejected. Used for past-due and expired accounts so historical
     * data stays reachable for download and reconciliation.
     */
    public function allowsWrite(): bool
    {
        return in_array($this, [self::TRIALING, self::ACTIVE], strict: true);
    }

    /**
     * Can the user sign in at all?
     *
     * Expired and past-due businesses are still allowed in — hiding the
     * dashboard entirely would make it impossible for an owner to understand
     * why they cannot work, or to reach the billing screen.
     */
    public function allowsAccess(): bool
    {
        return $this !== self::SUSPENDED;
    }

    public function label(): string
    {
        return match ($this) {
            self::TRIALING => 'Trial',
            self::ACTIVE => 'Active',
            self::PAST_DUE => 'Past due',
            self::CANCELED => 'Canceled',
            self::EXPIRED => 'Expired',
            self::SUSPENDED => 'Suspended',
        };
    }

    /**
     * Does the OWNER have to do something to restore write access?
     *
     * Drives the persistent "Action required" banner and the sidebar footer
     * prompt. Deliberately excludes SUSPENDED, because an operator action —
     * not a subscription change — is what unblocks that, so prompting the
     * owner to "update the payment method" there would be misleading.
     */
    public function needsAttention(): bool
    {
        return in_array($this, [self::PAST_DUE, self::EXPIRED], strict: true);
    }

    /**
     * Tailwind classes for the status badge.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::TRIALING => 'bg-sky-100 text-sky-700 ring-1 ring-inset ring-sky-200',
            self::ACTIVE => 'bg-emerald-100 text-emerald-700 ring-1 ring-inset ring-emerald-200',
            self::PAST_DUE => 'bg-amber-100 text-amber-800 ring-1 ring-inset ring-amber-200',
            self::CANCELED => 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200',
            self::EXPIRED => 'bg-rose-100 text-rose-700 ring-1 ring-inset ring-rose-200',
            self::SUSPENDED => 'bg-slate-800 text-white ring-1 ring-inset ring-slate-900',
        };
    }
}
