<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaintenanceRequestRequest;
use App\Http\Requests\UpdateMaintenanceRequestRequest;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Services\Tenancy\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

/**
 * Maintenance requests, always nested under the property that owns them.
 *
 * WHY EVERY ROUTE CARRIES `{property}`
 * ------------------------------------
 * A maintenance request id alone cannot tell the controller which address it
 * belongs to, so a `maintenanceRequests/{request}` URL would have to be
 * re‑parented in the controller and re‑checked against the active tenant.  Keeping
 * `{property}` in the path makes the relationship part of the URL: the binder
 * proves both records are in the active business, `MaintenancePolicy` proves the
 * parent agrees with the request, and `MaintenanceRequest` refuses to honour a
 * `property_id` in the payload.  Three layers, each of which is needed, none of
 * which can be the only one.
 *
 * Maintenance requests are managed on the property's own page rather than having
 * a separate index.  A landlord thinks in buildings, and "all 50 open jobs in the
 * portfolio" is a reporting question (Phase 7), not a management one.
 */
class MaintenanceController extends Controller
{
    public function __construct(
        protected BusinessContext $context,
    ) {}

    public function index(Request $request, Property $property): View
    {
        $this->authorize('viewAny', MaintenanceRequest::class);

        $search = $request->string('search')->toString() ?: null;

        $requests = MaintenanceRequest::query()
            ->where('property_id', $property->getKey())
            ->search($search)
            ->when($request->boolean('show_archived') === false, fn ($q) => $q->where('is_active', true))
            ->with(['property', 'tenant'])
            ->orderBy($this->sortColumn($request), $this->sortDirection($request))
            ->paginate(config('propertyhub.pagination.per_page'))
            ->withQueryString();

        return view('backoffice.maintenance.index', [
            'property' => $property,
            'requests' => $requests,
            'search' => $search,
            'showArchived' => $request->boolean('show_archived'),
            'canOpenRequests' => MaintenanceRequest::open()->count() > 0,
            'priorityOptions' => MaintenancePriority::cases(),
        });
    }

    public function create(Property $property): View
    {
        $this->authorize('createForProperty', $property);

        return view('backoffice.maintenance.create', [
            'property' => $property,
            'priorityOptions' => MaintenancePriority::cases(),
            'categoryOptions' => \App\Enums\MaintenanceCategory::cases(),
        ]);
    }

    public function store(MaintenanceRequestRequest $request, Property $property): RedirectResponse
    {
        // The request's `authorize()` already ran `createForProperty` check.
        // Enforce quota inside the same transaction as the create.
        DB::transaction(function () use ($request, $property): void {
            // `MaintenanceRequest::assertCanCreate` checks the plan quota.
            MaintenanceRequest::assertCanCreate($this->context);

            $request = new MaintenanceRequest([
                ...$request->maintenanceRequestAttributes(),
                'property_id' => $property->getKey(),
                'business_id' => $property->business_id, // stamped by BelongsToBusiness creating hook
            ]);

            $request->save();
        });

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', sprintf('Maintenance request %s was created.', $request->maintenanceRequestAttributes()['category']??'other'));
    }

    public function show(Property $property, MaintenanceRequest $request): View
    {
        $this->authorize('view', $request);

        return view('backoffice.maintenance.show', [
            'property' => $property,
            'request' => $request,
        ]);
    }

    public function edit(Property $property, MaintenanceRequest $request): View
    {
        $this->authorize('update', $request);

        return view('backoffice.maintenance.edit', [
            'property' => $property,
            'request' => $request,
            'priorityOptions' => MaintenancePriority::cases(),
            'categoryOptions' => \App\Enums\MaintenanceCategory::cases(),
        ]);
    }

    public function update(UpdateMaintenanceRequestRequest $request, Property $property, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        DB::transaction(function () use ($request, $property, $maintenanceRequest): void {
            // Enforce quota before saving – the same check that would have been
            // performed on create is re‑run here for data‑integrity.
            MaintenanceRequest::assertCanCreate($this->context);

            $maintenanceRequest->fill($request->maintenanceRequestAttributes())->save();
        });

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', sprintf('Maintenance request %s was updated.', $maintenanceRequest->categoryLabel()));
    }

    public function destroy(Property $property, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $this->authorize('delete', $maintenanceRequest);

        $label = $maintenanceRequest->categoryLabel();

        DB::transaction(function () use ($maintenanceRequest): void {
            $maintenanceRequest->delete();

            // Also soft-delete any associated audit entries? The maintenance service
            // handles its own retention; here we just delete the request record.
        });

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', sprintf('Maintenance request %s was removed.', $label));
    }

    /**
     * The only columns the list may be ordered by.
     */
    protected function sortColumn(Request $request): string
    {
        return match ($request->string('sort')->toString()) {
            'priority' => 'priority',
            'status' => 'status',
            'requested_at' => 'requested_at',
            'category' => 'category',
            default => 'requested_at',
        };
    }

    protected function sortDirection(Request $request): string
    {
        return $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
    }
}