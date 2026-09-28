<?php

namespace App\Enums;

/**
 * Status of a business' subscription. Stored in `subscriptions.status`.
 *
 * The state machine is intentionally one-directional apart from
 * `canceled -> active` (a user reactivating before the period ends):
 *
 *   trialing --> active      (trial converts)
 *   trialing --> expired     (trial runs out, no payment method)
 *   active   --> past_due    (invoice payment failed)
 *   active   --> canceled    (user cancels; access continues to period end)
 *   past_due --> active      (payment succeeds)
 *   past_due --> expired     (grace window elapses)
 *   canceled --> active      (user resumes before period end)
 *   any      --> expired     (period ends without renewal)
 *   any      --> paused      (admin holds the account)
 *
 * Transitions live in App\Services\Billing\SubscriptionState::transitionTo()
 * so that every path — webhook, CLI, admin screen — moves through the same
 * validation and audit trail.
 */
enum SubscriptionStatus: string
{
    case TRIALING = 'trialing';
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case CANCELED = 'canceled';
    case EXPIRED = 'expired';
    case PAUSED = 'paused';

    /**
     * States in which the business may create and modify data.
     */
    public function allowsWrite(): bool
    {
        return in_array($this, [self::TRIALING, self::ACTIVE], strict: true);
    }

    /**
     * States in which the business may sign in and read data.
     */
    public function allowsAccess(): bool
    {
        return $this !== self::PAUSED;
    }

    /**
     * Should the dashboard show a "your payment failed / renew" banner?
     */
    public function needsAttention(): bool
    {
        return in_array($this, [self::PAST_DUE, self::CANCELED, self::EXPIRED], strict: true);
    }

    /**
     * States from which no further transition is expected without new input.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::PAUSED], strict: true);
    }

    public function label(): string
    {
        return match ($this) {
            self::TRIALING => 'Trialing',
            self::ACTIVE => 'Active',
            self::PAST_DUE => 'Past due',
            self::CANCELED => 'Canceled',
            self::EXPIRED => 'Expired',
            self::PAUSED => 'Paused',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::TRIALING => 'bg-sky-100 text-sky-700 ring-1 ring-inset ring-sky-200',
            self::ACTIVE => 'bg-emerald-100 text-emerald-700 ring-1 ring-inset ring-emerald-200',
            self::PAST_DUE => 'bg-amber-100 text-amber-800 ring-1 ring-inset ring-amber-200',
            self::CANCELED => 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200',
            self::EXPIRED => 'bg-rose-100 text-rose-700 ring-1 ring-inset ring-rose-200',
            self::PAUSED => 'bg-slate-800 text-white ring-1 ring-inset ring-slate-900',
        };
    }

    /**
     * The legal transitions, used to validate state changes in one place.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::TRIALING => [self::ACTIVE, self::EXPIRED, self::PAUSED, self::CANCELED],
            self::ACTIVE => [self::PAST_DUE, self::CANCELED, self::EXPIRED, self::PAUSED],
            self::PAST_DUE => [self::ACTIVE, self::EXPIRED, self::CANCELED, self::PAUSED],
            self::CANCELED => [self::ACTIVE, self::EXPIRED, self::PAUSED],
            self::EXPIRED => [self::ACTIVE, self::PAUSED],
            self::PAUSED => [self::ACTIVE, self::TRIALING],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }
}
