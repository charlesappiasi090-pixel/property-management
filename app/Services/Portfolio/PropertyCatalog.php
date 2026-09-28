<?php

namespace App\Services\Portfolio;

use App\Enums\AuditEvent;
use App\Models\Business;
use App\Models\Property;
use App\Models\Unit;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\PlanQuota;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

/**
 * The only place a property or a unit is created, changed or removed.
 *
 * WHY A SERVICE INSTEAD OF CONTROLLERS DOING IT
 * ---------------------------------------------
 * Three rules are not the controller's job to enforce, and all three are
 * already encoded in the schema:
 *
 *   1. A quota is checked immediately before the INSERT, inside the same
 *      transaction. A middleware check happens before the form is even
 *      rendered; between that check and the insert another tab can commit, and
 *      the user is told they have room they do not have.
 *   2. A unit's `business_id` is copied from its property, never from input.
 *      This is the denormalisation described on the `units` migration, and if
 *      any caller can set it independently the two columns drift, after which
 *      the global scope starts hiding a landlord's own units.
 *   3. Every write is audited with its diff, so "who changed the rent on unit
 *      4?" has an answer.
 *
 * Controllers validate and authorise, then call one of these methods. Nothing
 * else may insert into `properties` or `units` — including tests, which is why
 * the factories are shaped to sit inside `BusinessContext::runFor()`.
 */
class PropertyCatalog
{
    public function __construct(
        protected PlanQuota $quota,
        protected AuditLogger $audit,
        protected BusinessContext $businessContext,
    ) {}

    /* ------------------------------------------------------------------ */
    /* Properties                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createProperty(array $attributes): Property
    {
        $business = $this->requireBusiness();

        return DB::transaction(function () use ($attributes, $business): Property {
            $this->quota->assertCanAdd($business, 'properties');

            $property = Property::createForBusiness($attributes);

            $this->audit->record(
                $property,
                AuditEvent::PROPERTY_CREATED->value,
                null,
                ['name' => $property->name, 'type' => $property->property_type->value],
                'Property added: '.$property->name,
            );

            return $property;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateProperty(Property $property, array $attributes): Property
    {
        $property->fill($attributes)->save();

        $this->audit->recordChanges(
            $property,
            AuditEvent::PROPERTY_UPDATED->value,
            'Property updated: '.$property->name,
            array_keys($attributes),
        );

        return $property->refresh();
    }

    /**
     * Archive a property together with its units.
     *
     * A hard delete is never offered. A property is financial history: the
     * moment Phase 4 attaches leases and payments to it, a hard delete either
     * cascades a landlord's books away or fails with a foreign-key error the
     * user cannot interpret.
     *
     * The units go in the same transaction as the property so the portfolio can
     * never be seen holding units whose parent has vanished — an orphaned unit
     * is indistinguishable from a bug, and "which building is Unit 4 in?" has
     * no answer once the parent is gone.
     */
    public function deleteProperty(Property $property): void
    {
        // Ownership assertion, see requireBusiness(). The return value is
        // unused because the row already knows its tenant; what matters is
        // that the check ran.
        $this->requireBusiness($property->business_id);

        $unitCount = $property->units()->count();

        DB::transaction(function () use ($property, $unitCount): void {
            $property->units()->get()->each(fn (Unit $unit) => $unit->delete());

            $property->delete();

            $this->audit->record(
                $property,
                AuditEvent::PROPERTY_DELETED->value,
                ['name' => $property->name, 'units' => $unitCount],
                null,
                sprintf(
                    'Property removed: %s (%d %s archived with it)',
                    $property->name,
                    $unitCount,
                    $unitCount === 1 ? 'unit' : 'units'
                ),
            );
        });
    }

    /* ------------------------------------------------------------------ */
    /* Units                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createUnit(Property $property, array $attributes): Unit
    {
        $business = $this->requireBusiness($property->business_id);

        /*
         * Rule 2. The property is the authority on the tenant and the caller
         * cannot disagree with it, so any `business_id` or `property_id` that
         * arrived in the payload is DROPPED rather than validated. A request
         * that tried to smuggle one gets a correct unit instead of a
         * validation error, because the invariant is not negotiable and so is
         * not a user mistake worth reporting.
         */
        unset($attributes['business_id'], $attributes['property_id']);

        /*
         * `commercial` and `land` have no residential inventory by definition
         * (`PropertyType::allowsResidentialUnits()`). `StoreUnitRequest`
         * refuses this with a form error; this is the same rule asserted where
         * it cannot be bypassed. A `LogicException` rather than a
         * `RuntimeException` because reaching it means a caller skipped the
         * request layer — an import or a future API endpoint — not that the
         * user did anything wrong.
         */
        if (! $property->property_type->allowsResidentialUnits()) {
            throw new LogicException(sprintf(
                'Cannot add a unit to property #%d: a %s property has no residential inventory.',
                $property->getKey(),
                $property->property_type->value
            ));
        }

        return DB::transaction(function () use ($attributes, $business, $property): Unit {
            $this->quota->assertCanAdd($business, 'units');

            $unit = new Unit([
                ...$attributes,
                'property_id' => $property->getKey(),
                'business_id' => $property->business_id,
            ]);

            $unit->save();

            $this->audit->record(
                $unit,
                AuditEvent::UNIT_CREATED->value,
                null,
                [
                    'label' => $unit->label,
                    'property' => $property->name,
                    'bedrooms' => $unit->bedrooms,
                    'monthly_rent' => $unit->monthly_rent,
                ],
                sprintf('Unit %s added to %s', $unit->label, $property->name),
            );

            return $unit;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateUnit(Unit $unit, array $attributes): Unit
    {
        $this->requireBusiness($unit->business_id);

        /*
         * A unit is never re-parented here. Moving one between properties
         * would change its tenant, and a move is a business event — money
         * moves with it — rather than an edit. If it is ever needed it gets its
         * own audited action.
         */
        unset($attributes['business_id'], $attributes['property_id']);

        $unit->fill($attributes)->save();

        $this->audit->recordChanges(
            $unit,
            AuditEvent::UNIT_UPDATED->value,
            'Unit updated: '.$unit->label,
            array_keys($attributes),
        );

        return $unit->refresh();
    }

    public function deleteUnit(Unit $unit): void
    {
        $this->requireBusiness($unit->business_id);

        $label = $unit->label;

        DB::transaction(function () use ($unit, $label): void {
            $unit->delete();

            $this->audit->record(
                $unit,
                AuditEvent::UNIT_DELETED->value,
                ['label' => $label],
                null,
                'Unit removed: '.$label,
            );
        });
    }

    /* ------------------------------------------------------------------ */
    /* Guards                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Resolve the active business, and assert that it owns the record in hand.
     *
     * The global scope already guarantees the record's `business_id` matches
     * the context, because a row from another tenant cannot be loaded at all.
     * The comparison is kept as an assertion anyway: it is what turns a future
     * refactor which weakens the scope from a silent data leak into a loud
     * failure.
     *
     * @param  int|null  $recordBusinessId  The tenant that owns the row being written, when there is one.
     */
    protected function requireBusiness(?int $recordBusinessId = null): Business
    {
        $business = $this->businessContext->business();

        if ($business === null) {
            throw new RuntimeException(
                'No active business in context. Property and unit writes must run inside '
                .'BusinessContext::runFor($business, ...).'
            );
        }

        if ($recordBusinessId !== null && (int) $recordBusinessId !== (int) $business->getKey()) {
            throw new RuntimeException(sprintf(
                'Refusing to write a property or unit owned by business #%d while business #%d is active.',
                $recordBusinessId,
                $business->getKey()
            ));
        }

        return $business;
    }
}
