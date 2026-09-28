<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentRequest;
use App\Models\Document;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\MaintenanceRequest;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;

/**
 * Document uploads – attached to any business‑scoped model.
 *
 * Documents are always nested under the parent record that owns them, which
 * gives us three layers of safety:
 *   1. Route model binding proves the parent exists and belongs to the active
 *      business (via `ResolveActiveBusiness` running before `SubstituteBindings`).
 *   2. `DocumentPolicy` proves the parent (and therefore the document) is in
 *      the same business as the active tenant.
 *   3. The controller explicitly enforces `canInBusiness` before write
 *      operations.
 *
 * No standalone document list page: documents are viewed from the parent
 * record's show page (e.g. `/properties/{property}/documents`).
 */
class DocumentController extends Controller
{
    public function __construct(
        protected BusinessContext $context,
    ) {}

    /**
     * List documents for a parent record.
     */
    public function index(Property $property): View
    {
        $this->authorize('viewAny', Document::class);

        $documents = Document::business()
            ->where('documentable_type', Property::class)
            ->where('documentable_id', $property->getKey())
            ->orderBy('created_at', 'desc')
            ->get();

        return view('backoffice.documents.index', [
            'property' => $property,
            'documents' => $documents,
        ]);
    }

    public function indexTenant(Tenant $tenant): View
    {
        $this->authorize('viewAny', Document::class);

        $documents = Document::business()
            ->where('documentable_type', Tenant::class)
            ->where('documentable_id', $tenant->getKey())
            ->orderBy('created_at', 'desc')
            ->get();

        return view('backoffice.documents.index', [
            'tenant' => $tenant,
            'documents' => $documents,
        ]);
    }

    public function indexLease(Lease $lease): View
    {
        $this->authorize('viewAny', Document::class);

        $documents = Document::business()
            ->where('documentable_type', Lease::class)
            ->where('documentable_id', $lease->getKey())
            ->orderBy('created_at', 'desc')
            ->get();

        return view('backoffice.documents.index', [
            'lease' => $lease,
            'documents' => $documents,
        ]);
    }

    public function indexPayment(Payment $payment): View
    {
        $this->authorize('viewAny', Document::class);

        $documents = Document::business()
            ->where('documentable_type', Payment::class)
            ->where('documentable_id', $payment->getKey())
            ->orderBy('created_at', 'desc')
            ->get();

        return view('backoffice.documents.index', [
            'payment' => $payment,
            'documents' => $documents,
        ]);
    }

    public function indexExpense(Expense $expense): View
    {
        $this->authorize('viewAny', Document::class);

        $documents = Document::business()
            ->where('documentable_type', Expense::class)
            ->where('documentable_id', $expense->getKey())
            ->orderBy('created_at', 'desc')
            ->get();

        return view('backoffice.documents.index', [
            'expense' => $expense,
            'documents' => $documents,
        ]);
    }

    public function indexMaintenance(MaintenanceRequest $request): View
    {
        $this->authorize('viewAny', Document::class);

        $documents = Document::business()
            ->where('documentable_type', MaintenanceRequest::class)
            ->where('documentable_id', $request->getKey())
            ->orderBy('created_at', 'desc')
            ->get();

        return view('backoffice.documents.index', [
            'maintenance' => $request,
            'documents' => $documents,
        ]);
    }

    /**
     * Show the upload form for a parent record.
     */
    public function create(Property $property): View
    {
        $this->authorize('create', Document::class);

        return view('backoffice.documents.create', [
            'property' => $property,
        ]);
    }

    public function createTenant(Tenant $tenant): View
    {
        $this->authorize('create', Document::class);

        return view('backoffice.documents.create', [
            'tenant' => $tenant,
        ]);
    }

    public function createLease(Lease $lease): View
    {
        $this->authorize('create', Document::class);

        return view('backoffice.documents.create', [
            'lease' => $lease,
        ]);
    }

    public function createPayment(Payment $payment): View
    {
        $this->authorize('create', Document::class);

        return view('backoffice.documents.create', [
            'payment' => $payment,
        ]);
    }

    public function createExpense(Expense $expense): View
    {
        $this->authorize('create', Document::class);

        return view('backoffice.documents.create', [
            'expense' => $expense,
        ]);
    }

    public function createMaintenance(MaintenanceRequest $request): View
    {
        $this->authorize('create', Document::class);

        return view('backoffice.documents.create', [
            'maintenance' => $request,
        ]);
    }

    /**
     * Store an uploaded document.
     *
     * The uploaded file is stored under `storage/app/public/docs/` and the path
     * (relative to `public`) is persisted.  A SoftDeletes record is created.
     */
    public function store(DocumentRequest $request, Property $property): RedirectResponse
    {
        Document::assertCanCreate($this->context);

        $data = $request->documentAttributes();

        $path = $request->file('file')->storeAs('public/docs', $data['storage_path']);

        $data['storage_path'] = Str::after($path, 'storage/');

        $document = Document::create($data);

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', 'Document uploaded.');
    }

    public function storeTenant(DocumentRequest $request, Tenant $tenant): RedirectResponse
    {
        Document::assertCanCreate($this->context);

        $data = $request->documentAttributes();

        $path = $request->file('file')->storeAs('public/docs', $data['storage_path']);

        $data['storage_path'] = Str::after($path, 'storage/');

        $document = Document::create($data);

        return redirect()
            ->route('app.tenants.show', $tenant)
            ->with('success', 'Document uploaded.');
    }

    public function storeLease(DocumentRequest $request, Lease $lease): RedirectResponse
    {
        Document::assertCanCreate($this->context);

        $data = $request->documentAttributes();

        $path = $request->file('file')->storeAs('public/docs', $data['storage_path']);

        $data['storage_path'] = Str::after($path, 'storage/');

        $document = Document::create($data);

        return redirect()
            ->route('app.leases.show', $lease)
            ->with('success', 'Document uploaded.');
    }

    public function storePayment(DocumentRequest $request, Payment $payment): RedirectResponse
    {
        Document::assertCanCreate($this->context);

        $data = $request->documentAttributes();

        $path = $request->file('file')->storeAs('public/docs', $data['storage_path']);

        $data['storage_path'] = Str::after($path, 'storage/');

        $document = Document::create($data);

        return redirect()
            ->route('app.payments.show', $payment)
            ->with('success', 'Document uploaded.');
    }

    public function storeExpense(DocumentRequest $request, Expense $expense): RedirectResponse
    {
        Document::assertCanCreate($this->context);

        $data = $request->documentAttributes();

        $path = $request->file('file')->storeAs('public/docs', $data['storage_path']);

        $data['storage_path'] = Str::after($path, 'storage/');

        $document = Document::create($data);

        return redirect()
            ->route('app.expenses.show', $expense)
            ->with('success', 'Document uploaded.');
    }

    public function storeMaintenance(DocumentRequest $request, MaintenanceRequest $maintenance): RedirectResponse
    {
        Document::assertCanCreate($this->context);

        $data = $request->documentAttributes();

        $path = $request->file('file')->storeAs('public/docs', $data['storage_path']);

        $data['storage_path'] = Str::after($path, 'storage/');

        $document = Document::create($data);

        return redirect()
            ->route('app.maintenance.show', $maintenance)
            ->with('success', 'Document uploaded.');
    }

    /**
     * Remove a document.
     */
    public function destroy(Property $property, Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $name = $document->original_name;

        $document->delete();

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', sprintf('Document %s was removed.', $name));
    }

    public function destroyTenant(Tenant $tenant, Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $name = $document->original_name;

        $document->delete();

        return redirect()
            ->route('app.tenants.show', $tenant)
            ->with('success', sprintf('Document %s was removed.', $name));
    }

    public function destroyLease(Lease $lease, Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $name = $document->original_name;

        $document->delete();

        return redirect()
            ->route('app.leases.show', $lease)
            ->with('success', sprintf('Document %s was removed.', $name));
    }

    public function destroyPayment(Payment $payment, Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $name = $document->original_name;

        $document->delete();

        return redirect()
            ->route('app.payments.show', $payment)
            ->with('success', sprintf('Document %s was removed.', $name));
    }

    public function destroyExpense(Expense $expense, Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $name = $document->original_name;

        $document->delete();

        return redirect()
            ->route('app.expenses.show', $expense)
            ->with('success', sprintf('Document %s was removed.', $name));
    }

    public function destroyMaintenance(MaintenanceRequest $maintenance, Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $name = $document->original_name;

        $document->delete();

        return redirect()
            ->route('app.maintenance.show', $maintenance)
            ->with('success', sprintf('Document %s was removed.', $name));
    }
}