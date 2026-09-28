<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Policies\Concerns\AuthorisesTenantRecords;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Authorisation for units.
 *
 * A unit is nested inside a property, and that is not only a URL shape: a unit's
 * permissions are checked against the business that owns ITSELF, and
 * `guardTenant()` proves the unit is in the active business before any of that.
 * Because the unit carries a denormalised `business_id` (see the `units`
 * migration), a bug that let a unit point at another tenant's property would
 * otherwise produce a unit visible in two landlords' portfolios, so every
 * ability also re-checks the PARENT.
 *
 * Creation is authorised against the *property* rather than a blank `Unit`.
 * Laravel hands a policy an unsaved, attribute-less model for `create`, and a
 * blank unit has no `business_id` — so the check would always fail, or worse,
 * would pass because someone "fixed" it by skipping the membership test.
 * `createForProperty()` names the actual subject of the action.
 *
 * The `properties.*` permission is deliberately NOT required to manage units.
 * `units.*` stands on its own in the role matrix: Phase 6 maintenance staff need
 * to look up "which unit is this repair in?" without being able to change rent.
 */
class UnitPolicy
{
    use AuthorisesTenantRecords;

    public function viewAny(User $user): bool
    {
        $business = $this->activeBusiness();

        return $business !== null && $user->canInBusiness(PermissionName::UNITS_VIEW, $business);
    }

    public function view(User $user, Unit $unit): bool
    {
        $this->guardTenant($unit);
        $this->guardParentTenant($unit);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $unit)
            && $user->canInBusiness(PermissionName::UNITS_VIEW, $unit->business_id);
    }

    /**
     * Add a unit to this property.
     */
    public function createForProperty(User $user, Property $property): bool
    {
        $this->guardTenant($property);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $property)
            && $user->canInBusiness(PermissionName::UNITS_CREATE, $property->business_id);
    }

    public function update(User $user, Unit $unit): bool
    {
        $this->guardTenant($unit);
        $this->guardParentTenant($unit);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $unit)
            && $user->canInBusiness(PermissionName::UNITS_UPDATE, $unit->business_id);
    }

    public function delete(User $user, Unit $unit): bool
    {
        $this->guardTenant($unit);
        $this->guardParentTenant($unit);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $unit)
            && $user->canInBusiness(PermissionName::UNITS_DELETE, $unit->business_id);
    }

    /**
     * Prove the unit's PARENT property belongs to the active business too.
     *
     * `guardTenant()` above checks only the unit's own `business_id`. Those two
     * columns are written together and are meant never to disagree, but they
     * are two columns rather than one, and this is the check that means a
     * disagreement cannot surface as a unit visible in two portfolios.
     *
     * @throws ModelNotFoundException
     */
    protected function guardParentTenant(Unit $unit): void
    {
        $property = $unit->property;

        if ($property === null) {
            /*
             * A unit whose property row is gone cannot be shown safely.
             * `PropertyCatalog::deleteProperty()` is written so this state is
             * not reachable, which makes it a 404 rather than a crash: if it
             * ever happens, the data is already wrong and a stack trace would
             * tell the user nothing about why.
             */
            throw (new ModelNotFoundException)->setModel(Property::class);
        }

        $this->guardTenant($property);
    }
}
