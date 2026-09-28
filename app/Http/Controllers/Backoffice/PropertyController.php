<?php

namespace App\Http\Controllers\Backoffice;

use App\Enums\PropertyType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Models\Property;
use App\Services\Billing\PlanQuota;
use App\Services\Portfolio\PropertyCatalog;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Properties: the top of the physical portfolio.
 *
 * TENANT SAFETY
 * -------------
 * `{property}` is bound by name, and the binding goes through the
 * `BelongsToBusiness` global scope, so a property belonging to another
 * landlord is not found at all — the user gets a 404, not a 403, and cannot
 * learn from the response that the id exists. The policies then re-assert the
 * tenant (`guardTenant()`) so the guarantee does not depend on the binder
 * being the only place it is checked.
 *
 * All writes go through `PropertyCatalog`, which owns the quota check, the
 * audit trail and the rule that a unit's tenant comes from its property. This
 * controller validates, authorises and redirects; it does not touch the
 * database.
 */
class PropertyController extends Controller
{
    public function __construct(
        protected BusinessContext $context,
        protected PropertyCatalog $catalog,
        protected PlanQuota $quota,
    ) {}

    public function index(Request $request): View
    {
        $business = $this->context->businessOrFail();

        $this->authorize('viewAny', Property::class);

        $search = $request->string('search')->toString() ?: null;

        $properties = Property::query()
            ->search($search)
            // Default to the working portfolio. Archived properties are real
            // records and remain reachable from their own page, but they are
            // not what a landlord opens this screen to see.
            ->unless($request->boolean('show_archived'), fn ($q) => $q->where('is_active', true))
            ->withCount(['units' => fn ($q) => $q->where('is_active', true)])
            ->orderBy($this->sortColumn($request), $this->sortDirection($request))
            ->paginate(config('propertyhub.pagination.per_page'))
            ->withQueryString();

        return view('backoffice.properties.index', [
            'business' => $business,
            'properties' => $properties,
            'search' => $search,
            'showArchived' => $request->boolean('show_archived'),
            'propertyTypes' => PropertyType::cases(),
            'quota' => [
                'used' => $this->quota->usageFor($business, 'properties'),
                'limit' => $this->quota->limitFor($business, 'properties'),
            ],
        ]);
    }

    public function create(): View
    {
        $business = $this->context->businessOrFail();

        $this->authorize('create', Property::class);

        return view('backoffice.properties.create', [
            'business' => $business,
            'propertyTypes' => PropertyType::cases(),
        ]);
    }

    public function store(StorePropertyRequest $request): RedirectResponse
    {
        // The request's `authorize()` already ran the `create` policy.
        $property = $this->catalog->createProperty($request->propertyAttributes());

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', sprintf('%s was added to your portfolio.', $property->name));
    }

    public function show(Property $property): View
    {
        $this->authorize('view', $property);

        $business = $this->context->businessOrFail();

        /*
         * Units are loaded in full rather than paginated.
         *
         * A property is a building, and a building has tens of units, not
         * thousands. A paginator here would put a page control inside the one
         * screen whose job is "show me this building's inventory", and the unit
         * quota is small enough that the whole set fits. If a single property
         * ever exceeds a few hundred units, this is the line to change.
         */
        $units = $property->units()
            ->orderBy('is_active', 'desc')
            ->orderBy('label')
            ->get();

        return view('backoffice.properties.show', [
            'business' => $business,
            'property' => $property,
            'units' => $units,
            'activeUnitCount' => $units->where('is_active', true)->count(),
            'unitQuota' => [
                'used' => $this->quota->usageFor($business, 'units'),
                'limit' => $this->quota->limitFor($business, 'units'),
            ],
            // May this user ADD a unit here? Updating and removing an existing
            // unit are checked per row in the view, against the unit itself,
            // because those abilities belong to the unit's policy.
            'canAddUnit' => request()->user()->can('createForProperty', $property),
        ]);
    }

    public function edit(Property $property): View
    {
        $this->authorize('update', $property);

        return view('backoffice.properties.edit', [
            'business' => $this->context->businessOrFail(),
            'property' => $property,
            'propertyTypes' => PropertyType::cases(),
        ]);
    }

    public function update(UpdatePropertyRequest $request, Property $property): RedirectResponse
    {
        $property = $this->catalog->updateProperty($property, $request->propertyAttributes());

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', sprintf('%s was updated.', $property->name));
    }

    public function destroy(Property $property): RedirectResponse
    {
        $this->authorize('delete', $property);

        $name = $property->name;

        // A soft delete, archiving the units with it. See
        // `PropertyCatalog::deleteProperty()`.
        $this->catalog->deleteProperty($property);

        return redirect()
            ->route('app.properties.index')
            ->with('success', sprintf('%s was removed from your portfolio.', $name));
    }

    /**
     * The only columns the list may be ordered by.
     *
     * `sortColumn()` would otherwise hand a query-string value straight to
     * `orderBy()`. Laravel quotes the identifier, so this is not an injection
     * vector — but an unknown column produces a `QueryException` and a 500,
     * and a hand-edited `?sort=` is not an exceptional event. Anything not
     * listed falls back to the default.
     */
    protected function sortColumn(Request $request): string
    {
        return match ($request->string('sort')->toString()) {
            'name' => 'name',
            'city' => 'city',
            'type' => 'property_type',
            'units' => 'units_count',
            'created_at' => 'created_at',
            default => 'name',
        };
    }

    protected function sortDirection(Request $request): string
    {
        return $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
    }
}
