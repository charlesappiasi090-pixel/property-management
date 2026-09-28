<?php

namespace App\Services\Billing;

use App\Models\Business;
use App\Services\Tenancy\BusinessMembershipService;
use App\Support\Tenancy\BusinessContext;
use RuntimeException;

/**
 * The single authority on "has this business used up its plan yet?".
 *
 * WHY THIS IS A SERVICE AND NOT A MIDDLEWARE CHECK
 * -----------------------------------------------
 * A quota question ("can I add a 6th property when the plan allows 5?")
 * cannot be answered by `EnsureSubscriptionIsActive`, which only knows the
 * subscription is alive. It has to be asked at the point of the write, inside
 * the transaction that performs it, or two concurrent requests will each see
 * 5 of 5 properties and both insert a sixth.
 *
 * Callers therefore use `assertCanAdd()` immediately before creating, and
 * create inside the same transaction. `PlanQuota::remaining()` is also
 * exposed to the UI so the "5 of 5 properties used" counter is computed by
 * exactly the same code that enforces the limit.
 */
class PlanQuota
{
    /**
     * Resources that have a plan quota, mapped to the column on `plans` and
     * the count query needed to measure current usage.
     *
     * @var array<string, array{column: string, relation: string, label: string}>
     */
    protected const RESOURCES = [
        'properties' => ['column' => 'max_properties', 'relation' => 'properties', 'label' => 'property'],
        'units' => ['column' => 'max_units', 'relation' => 'units', 'label' => 'unit'],
        'staff' => ['column' => 'max_staff', 'relation' => 'members', 'label' => 'staff member'],
        'documents' => ['column' => 'max_documents', 'relation' => 'documents', 'label' => 'document'],
    ];

    public function __construct(protected BusinessContext $businessContext) {}

    /**
     * The plan's limit for a resource, or null when unlimited.
     */
    public function limitFor(Business $business, string $resource): ?int
    {
        $definition = self::RESOURCES[$resource] ?? null;

        if ($definition === null) {
            return null;
        }

        $limit = $business->subscription?->plan?->{$definition['column']};

        return $limit === null ? null : (int) $limit;
    }

    /**
     * How many of a resource the business currently has.
     */
    public function usageFor(Business $business, string $resource): int
    {
        $definition = self::RESOURCES[$resource] ?? null;

        if ($definition === null) {
            return 0;
        }

        return method_exists($business, $definition['relation'])
            ? $business->{$definition['relation']}()->count()
            : 0;
    }

    /**
     * How many more may be added, or null when unlimited.
     */
    public function remaining(Business $business, string $resource): ?int
    {
        $limit = $this->limitFor($business, $resource);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->usageFor($business, $resource));
    }

    /**
     * Has the quota for this resource been exhausted?
     */
    public function isExhausted(Business $business, string $resource): bool
    {
        $limit = $this->limitFor($business, $resource);

        if ($limit === null) {
            return false;
        }

        return $this->usageFor($business, $resource) >= $limit;
    }

    /**
     * Guard a write. Throws with a message the UI can show verbatim.
     *
     * @throws QuotaExceededException
     */
    public function assertCanAdd(Business $business, string $resource, int $amount = 1): void
    {
        $definition = self::RESOURCES[$resource] ?? null;

        if ($definition === null) {
            return;
        }

        $limit = $this->limitFor($business, $resource);

        if ($limit === null) {
            return;
        }

        $usage = $this->usageFor($business, $resource);

        if ($usage + $amount <= $limit) {
            return;
        }

        throw new QuotaExceededException(
            sprintf(
                'Your %s plan allows %d %s%s. You currently have %d. Upgrade your plan to add more.',
                $business->subscription?->plan?->name ?? 'current',
                $limit,
                $definition['label'],
                $limit === 1 ? '' : 's',
                $usage
            )
        );
    }

    /**
     * Is a plan-level capability enabled? Reads the plan's `features` JSON.
     */
    public function allowsFeature(string $feature): bool
    {
        $plan = $this->businessContext->business()?->subscription?->plan;

        if ($plan === null) {
            return false;
        }

        return $plan->allows($feature);
    }

    /**
     * Guard a feature-gated area, for capability checks that are not quotas.
     *
     * @throws FeatureNotAvailableException
     */
    public function assertFeature(string $feature, ?string $humanName = null): void
    {
        if ($this->allowsFeature($feature)) {
            return;
        }

        throw new FeatureNotAvailableException(
            sprintf(
                'The "%s" feature is not included in your current plan. Upgrade to unlock it.',
                $humanName ?? str($feature)->replace('_', ' ')->headline()->toString()
            )
        );
    }
}
