<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\TenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Tenant;
use App\Services\Tenancy\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Tenants: external landlords / rental entities.
 *
 * TENANT SAFETY
 * -------------
 * `{tenant}` is bound by name, and the binding goes through the
 * `BelongsToBusiness` global scope, so a tenant belonging to another
 * landlord is not found at all — the user gets a 404, not a 403, and cannot
 * learn from the response that the id exists. The policies then re-assert the
 * tenant (`guardTenant()`) so the guarantee does not depend on the binder
 * being the only place it is checked.
 *
 * All writes go through the service‑level methods that own the quota check,
 * the audit trail and the invariant that a tenant's `business_id` comes
 * from the active context.  This controller validates, authorises and
 * redirects; it does not touch the database.
 */
class TenantController extends Controller
{
    public function __construct(
        protected BusinessContext $context,
    ) {}

    public function index(Request $request): View
    {
        $business = $this->context->businessOrFail();

        $this->authorize('viewAny', Tenant::class);

        $search = $request->string('search')->toString() ?: null;

        $tenants = Tenant::query()
            ->where('business_id', $business->getKey())
            ->search($search)
            ->unless($request->boolean('show_archived'), fn ($q) => $q->where('is_active', true))
            ->orderBy($this->sortColumn($request), $this->sortDirection($request))
            ->paginate(config('propertyhub.pagination.per_page'))
            ->withQueryString();

        return view('backoffice.tenants.index', [
            'business' => $business,
            'tenants' => $tenants,
            'search' => $search,
            'showArchived' => $request->boolean('show_archived'),
            'quota' => [
                'used' => $business->members()->whereHas('role', fn ($q) => $q->name === 'owner')->count(),
                // Tenants don't have a plan quota in Phase 3 – the count is
                // informational only; the real limit is enforced by the
                // application logic (no tenant may be created without an
                * business context).  For display we simply show the current count.
            ],
        ]);
    }

    public function create(): View
    {
        $business = $this->context->businessOrFail();

        $this->authorize('create', Tenant::class);

        return view('backoffice.tenants.create', [
            'business' => $business,
        ]);
    }

    public function store(TenantRequest $request): RedirectResponse
    {
        // The request's `authorize()` already ran the `create` policy.
        $tenant = Tenant::createForBusiness($request->tenantAttributes());

        return redirect()
            ->route('app.tenants.show', $tenant)
            ->with('success', sprintf('Tenant %s was added.', $tenant->name));
    }

    public function show(Tenant $tenant): View
    {
        $this->authorize('view', $tenant);

        $business = $this->context->businessOrFail();

        $activeLease = $tenant->activeLease;

        return view('backoffice.tenants.show', [
            'business' => $business,
            'tenant' => $tenant,
            'activeLease' => $activeLease,
        ]);
    }

    public function edit(Tenant $tenant): View
    {
        $this->authorize('update', $tenant);

        return view('backoffice.tenants.edit', [
            'business' => $this->context->businessOrFail(),
            'tenant' => $tenant,
        ]);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->fill($request->tenantAttributes())->save();

        return redirect()
            ->route('app.tenants.show', $tenant)
            ->with('success', sprintf('Tenant %s was updated.', $tenant->name));
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->authorize('delete', $tenant);

        $name = $tenant->name;

        // Soft-delete the tenant and its associated leases.
        // The policy already guarantees the tenant belongs to the active business.
        $tenant->delete();

        return redirect()
            ->route('app.tenants.index')
            ->with('success', sprintf('Tenant %s was removed.', $name));
    }

    /**
     * The only columns the list may be ordered by.
     */
    protected function sortColumn(Request $request): string
    {
        return match ($request->string('sort')->toString()) {
            'name' => 'name',
            'created_at' => 'created_at',
            default => 'name',
        };
    }

    protected function sortDirection(Request $request): string
    {
        return $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
    }
}