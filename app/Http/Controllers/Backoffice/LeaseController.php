<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeaseRequest;
use App\Http\Requests\UpdateLeaseRequest;
use App\Models\Lease;
use App\Models\Property;
use App\Services\Tenancy\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

/**
 * Leases, always nested under the property that owns them.
 *
 * WHY NESTED UNDER `/properties/{property}`
 * -----------------------------------------
 * A lease is always associated with a property (and optionally a unit).
 * Keeping `{property}` in the path makes the relationship part of the URL:
 * the binder proves both records are in the active business, `LeasePolicy`
 * proves the parent agrees with the lease, and `PropertyCatalog` (or the
 * direct `Lease::assertCanCreate`) refuses to honour a `property_id` in the
 * payload that disagrees. Three layers, each of which is needed, none of which
 * can be the only one.
 *
 * Units are managed on the property's own page rather than having a separate
 * index. A landlord thinks in buildings, and "all 40 leases in the portfolio"
 * is a reporting question (Phase 7 rent roll), not a management one.
 */
class LeaseController extends Controller
{
    public function __construct(
        protected BusinessContext $context,
    ) {}

    public function index(Request $request, Property $property): View
    {
        $this->authorize('viewAny', Lease::class);

        $search = $request->string('search')->toString() ?: null;

        $leases = Lease::query()
            ->where('property_id', $property->getKey())
            ->search($search)
            ->when($request->boolean('show_archived') === false, fn ($q) => $q->where('is_active', true))
            ->with('tenant')
            ->orderBy($this->sortColumn($request), $this->sortDirection($request))
            ->paginate(config('propertyhub.pagination.per_page'))
            ->withQueryString();

        $property = $property; // for the view

        return view('backoffice.leases.index', [
            'property' => $property,
            'leases' => $leases,
            'search' => $search,
            'showArchived' => $request->boolean('show_archived'),
            'canManageLeases' => $request->user()->can('create', Lease::class),
            'quota' => [
                'used' => $this->context->business()?->leases()->count() ?? 0,
                'limit' => null, // leases may not have a plan limit in Phase 3
            ],
        ]);
    }

    public function create(Property $property): View
    {
        $this->authorize('createForProperty', $property);

        return view('backoffice.leases.create', [
            'business' => $this->context->businessOrFail(),
            'property' => $property,
        ]);
    }

    public function store(LeaseRequest $request, Property $property): RedirectResponse
    {
        // The request's `authorize()` already ran `createForProperty` check.
        // Enforce quota inside the same transaction as the create.
        DB::transaction(function () use ($request, $property): void {
            // `Lease::assertCanCreate` checks the plan quota.
            Lease::assertCanCreate($this->context);

            $lease = new Lease([
                ...$request->leaseAttributes(),
                'property_id' => $property->getKey(),
                'business_id' => $property->business_id, // stamped by BelongsToBusiness creating hook if not set, but explicit here
            ]);

            $lease->save();
        });

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', sprintf('Lease for %s was added.', $property->name));
    }

    public function show(Property $property, Lease $lease): View
    {
        $this->authorize('view', $lease);

        return view('backoffice.leases.show', [
            'property' => $property,
            'lease' => $lease,
        ]);
    }

    public function edit(Property $property, Lease $lease): View
    {
        $this->authorize('update', $lease);

        return view('backoffice.leases.edit', [
            'business' => $this->context->businessOrFail(),
            'property' => $property,
            'lease' => $lease,
        ]);
    }

    public function update(UpdateLeaseRequest $request, Property $property, Lease $lease): RedirectResponse
    {
        DB::transaction(function () use ($request, $property, $lease): void {
            // Enforce quota before saving – the same check that would have been
            // performed on create is re‑run here for data‑integrity.
            Lease::assertCanCreate($this->context);

            $lease->fill($request->leaseAttributes())->save();
        });

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', sprintf('Lease %s was updated.', $lease->tenant->displayName()));
    }

    public function destroy(Property $property, Lease $lease): RedirectResponse
    {
        $this->authorize('delete', $lease);

        $label = $lease->tenant->displayName();

        DB::transaction(function () use ($lease): void {
            $lease->delete();

            // Also soft-delete any associated audit entries? The audit service
            // handles its own retention; here we just delete the lease record.
        });

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', sprintf('Lease for %s was removed.', $label));
    }

    /**
     * The only columns the list may be ordered by.
     */
    protected function sortColumn(Request $request): string
    {
        return match ($request->string('sort')->toString()) {
            'tenant' => 'tenants.name',
            'start_date' => 'start_date',
            'status' => 'status',
            'created_at' => 'created_at',
            default => 'start_date',
        };
    }

    protected function sortDirection(Request $request): string
    {
        return $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
    }
}