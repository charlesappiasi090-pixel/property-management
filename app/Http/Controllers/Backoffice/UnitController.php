<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Models\Property;
use App\Models\Unit;
use App\Services\Portfolio\PropertyCatalog;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Units, always nested under the property that owns them.
 *
 * WHY EVERY ROUTE CARRIES `{property}`
 * -----------------------------------
 * A unit id alone cannot tell the controller which address it belongs to, so a
 * `units/{unit}` URL would have to be re-parented in the controller and
 * re-checked against the active tenant. Keeping `{property}` in the path makes
 * the relationship part of the URL: the binder proves both records are in the
 * active business, `UnitPolicy` proves the parent agrees with the unit, and
 * `PropertyCatalog` refuses to honour a `property_id` in the payload. Three
 * layers, each of which is needed, none of which can be the only one.
 *
 * Units are managed on the property's own page rather than having a separate
 * index. A landlord thinks in buildings, and "all 40 units in the portfolio" is
 * a reporting question (Phase 7 rent roll), not a management one.
 */
class UnitController extends Controller
{
    public function __construct(
        protected BusinessContext $context,
        protected PropertyCatalog $catalog,
    ) {}

    public function create(Property $property): View
    {
        $this->authorize('createForProperty', $property);

        return view('backoffice.properties.units.create', [
            'business' => $this->context->businessOrFail(),
            'property' => $property,
        ]);
    }

    public function store(StoreUnitRequest $request, Property $property): RedirectResponse
    {
        // The request's `authorize()` ran `createForProperty` already.
        $unit = $this->catalog->createUnit($property, $request->unitAttributes());

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', sprintf('Unit %s was added to %s.', $unit->label, $property->name));
    }

    public function edit(Property $property, Unit $unit): View
    {
        $this->authorize('update', $unit);
        $this->assertUnitBelongsToProperty($property, $unit);

        return view('backoffice.properties.units.edit', [
            'business' => $this->context->businessOrFail(),
            'property' => $property,
            'unit' => $unit,
        ]);
    }

    public function update(UpdateUnitRequest $request, Property $property, Unit $unit): RedirectResponse
    {
        $unit = $this->catalog->updateUnit($unit, $request->unitAttributes());

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', sprintf('Unit %s was updated.', $unit->label));
    }

    public function destroy(Property $property, Unit $unit): RedirectResponse
    {
        $this->authorize('delete', $unit);
        $this->assertUnitBelongsToProperty($property, $unit);

        $label = $unit->label;

        $this->catalog->deleteUnit($unit);

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', sprintf('Unit %s was removed from %s.', $label, $property->name));
    }

    /**
     * 404 unless the unit really hangs off this property.
     *
     * The two are bound independently, so `/app/properties/1/units/9` is a valid
     * URL for unit 9 whether it belongs to property 1 or not. Both are in the
     * active business — the scope proves that — so this is not a tenant check.
     * It stops a stale link, or a mistyped id, from renaming one building's
     * inventory with another's URL in the message the user reads afterwards.
     */
    protected function assertUnitBelongsToProperty(Property $property, Unit $unit): void
    {
        if ((int) $unit->property_id !== (int) $property->getKey()) {
            throw new NotFoundHttpException;
        }
    }
}
