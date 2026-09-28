<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Models\RentRoll;
use App\Services\Tenancy\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Rent roll snapshots – a denormalised view of all active leases at a point in time.
 *
 * RENT ROLL SNAPSHOTS ARE NESTED under the property that owns them, mirroring
 * the pattern used for maintenance requests and expenses.  Keeping `{property}`
 * in the path makes the relationship part of the URL: the binder proves both
 * records are in the active business, `ReportPolicy` proves the parent agrees
 * with the snapshot, and the controller enforces that a rent‑roll cannot exist
 * without a property.  Three layers, each of which is needed, none of which can
 * be the only one.
 *
 * No rent‑roll index page: a rent roll is a point‑in‑time snapshot, not a
 * CRUD screen.  Portfolio‑wide roll reporting is the "rent roll" report in
 * Phase 8, not a second CRUD screen.
 */
class ReportController extends Controller
{
    public function __construct(
        protected BusinessContext $context,
    ) {}

    public function index(Request $request, Property $property): View
    {
        $this->authorize('viewAny', RentRoll::class);

        $search = $request->string('search')->toString() ?: null;

        $rolls = RentRoll::query()
            ->where('property_id', $property->getKey())
            ->search($search)
            ->when($request->boolean('show_archived') === false, fn ($q) => $q->where('is_active', true))
            ->with(['property', 'tenant', 'lease'])
            ->orderBy($this->sortColumn($request), $this->sortDirection($request))
            ->paginate(config('propertyhub.pagination.per_page'))
            ->withQueryString();

        return view('backoffice.reports.index', [
            'property' => $property,
            'rolls' => $rolls,
            'search' => $search,
            'showArchived' => $request->boolean('show_archived'),
            'metrics' => [
                'total_rolls' => $rolls->total(),
                'active_rolls' => $rolls->where('lease_status', 'active')->count(),
                'terminated_rolls' => $rolls->where('lease_status', 'terminated')->count(),
                'total_monthly_rent' => $rolls->where('lease_status', 'active')
                    ->sum('monthly_rent')
                    ?->float??0,
            ],
        ]);
    }

    public function create(Property $property): View
    {
        $this->authorize('createForProperty', $property);

        return view('backoffice.reports.create', [
            'property' => $property,
            'snapshot_at' => now(),
        ]);
    }

    public function store(ReportRequest $request, Property $property): RedirectResponse
    {
        // The request's `authorize()` already ran `createForProperty` check.
        // Enforce quota inside the same transaction as the create.
        DB::transaction(function () use ($request, $property): void {
            // `RentRoll::assertCanCreate` checks the plan quota.
            RentRoll::assertCanCreate($this->context);

            $roll = new RentRoll([
                ...$request->rentRollAttributes(),
                'property_id' => $property->getKey(),
                'business_id' => $property->business_id, // stamped by BelongsToBusiness creating hook
                'snapshot_at' => now(),
            ]);

            $roll->save();
        });

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', 'Rent roll snapshot created.');
    }

    public function show(Property $property, RentRoll $roll): View
    {
        $this->authorize('view', $roll);

        return view('backoffice.reports.show', [
            'property' => $property,
            'roll' => $roll,
        ]);
    }

    public function edit(Property $property, RentRoll $roll): View
    {
        $this->authorize('update', $roll);

        return view('backoffice.reports.edit', [
            'property' => $property,
            'roll' => $roll,
        ]);
    }

    public function update(ReportRequest $request, Property $property, RentRoll $roll): RedirectResponse
    {
        DB::transaction(function () use ($request, $property, $roll): void {
            // Enforce quota before saving – the same check that would have been
            // performed on create is re‑run here for data‑integrity.
            RentRoll::assertCanCreate($this->context);

            $roll->fill($request->rentRollAttributes())->save();
        });

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', 'Rent roll snapshot updated.');
    }

    public function destroy(Property $property, RentRoll $roll): RedirectResponse
    {
        $this->authorize('delete', $roll);

        $snapshot_at = $roll->snapshot_at;

        DB::transaction(function () use ($roll): void {
            $roll->delete();

            // Also soft-delete any associated audit entries? The report service
            // handles its own retention; here we just delete the rent‑roll record.
        });

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', sprintf('Rent roll snapshot %s was removed.', $snapshot_at->format('m/d/Y')));
    }

    /**
     * The only columns the list may be ordered by.
     */
    protected function sortColumn(Request $request): string
    {
        return match ($request->string('sort')->toString()) {
            'tenant_name' => 'tenant_name',
            'snapshot_at' => 'snapshot_at',
            'lease_status' => 'lease_status',
            'monthly_rent' => 'monthly_rent',
            'created_at' => 'created_at',
            default => 'snapshot_at',
        };
    }

    protected function sortDirection(Request $request): string
    {
        return $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
    }
}