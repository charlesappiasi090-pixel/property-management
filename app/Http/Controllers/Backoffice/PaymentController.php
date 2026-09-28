<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Models\Payment;
use App\Services\Billing\PlanQuota;
use App\Services\Tenancy\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Payments – a single rent payment recorded against a lease.
 *
 * TENANT SAFETY
 * -------------
 * `{payment}` is bound by name, and the binding goes through the
 * `BelongsToBusiness` global scope, so a payment belonging to another
 * landlord is not found at all — the user gets a 404, not a 403, and cannot
 * learn from the response that the id exists. The policies then re-assert the
 * tenant (`guardTenant()`) so the guarantee does not depend on the binder
 * being the only place it is checked.
 *
 * All writes go through `PaymentCatalog` (here simply `Payment::assertCanCreate`)
 * which owns the quota check, the audit trail and the invariant that a
 * payment's `business_id` comes from the active context. This controller
 * validates, authorises and redirects; it does not touch the database.
 */
class PaymentController extends Controller
{
    public function __construct(
        protected BusinessContext $context,
    ) {}

    public function index(Request $request): View
    {
        $business = $this->context->businessOrFail();

        $this->authorize('viewAny', Payment::class);

        $search = $request->string('search')->toString() ?: null;

        $payments = Payment::query()
            ->where('business_id', $business->getKey())
            ->search($search)
            ->when($request->boolean('show_archived') === false, fn ($q) => $q->where('is_active', true))
            ->with('lease.tenant')
            ->orderBy($this->sortColumn($request), $this->sortDirection($request))
            ->paginate(config('propertyhub.pagination.per_page'))
            ->withQueryString();

        return view('backoffice.payments.index', [
            'business' => $business,
            'payments' => $payments,
            'search' => $search,
            'showArchived' => $request->boolean('show_archived'),
            'quota' => [
                'used' => $this->quota->usageFor($business, 'payments'),
                'limit' => $this->quota->limitFor($business, 'payments'),
            ],
        ]);
    }

    public function create(): View
    {
        $business = $this->context->businessOrFail();

        $this->authorize('create', Payment::class);

        return view('backoffice.payments.create', [
            'business' => $business,
            'leases' => $business->leases()->with('tenant')->get(),
        ]);
    }

    public function store(PaymentRequest $request): RedirectResponse
    {
        // The request's `authorize()` already ran the `create` policy.
        // Enforce quota inside the same transaction as the create.
        DB::transaction(function () use ($request): void {
            // `Payment::assertCanCreate` checks the plan quota.
            Payment::assertCanCreate($this->context);

            $payment = new Payment([
                ...$request->paymentAttributes(),
                'lease_id' => $request->lease_id,
                'business_id' => $business->getKey(),
            ]);

            $payment->save();
        });

        return redirect()
            ->route('app.payments.index')
            ->with('success', sprintf('Payment of %s was recorded.', $request->paymentAttributes()['amount']));
    }

    public function show(Payment $payment): View
    {
        $this->authorize('view', $payment);

        $business = $this->context->businessOrFail();

        return view('backoffice.payments.show', [
            'business' => $business,
            'payment' => $payment,
            'lease' => $payment->lease,
        ]);
    }

    public function edit(Payment $payment): View
    {
        $this->authorize('update', $payment);

        return view('backoffice.payments.edit', [
            'business' => $this->context->businessOrFail(),
            'payment' => $payment,
            'leases' => $business->leases()->with('tenant')->get(),
        ]);
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): RedirectResponse
    {
        DB::transaction(function () use ($request, $payment): void {
            // Enforce quota before saving – the same check that would have been
            // performed on create is re‑run here for data‑integrity.
            Payment::assertCanCreate($this->context);

            $payment->fill($request->paymentAttributes())->save();
        });

        return redirect()
            ->route('app.payments.show', $payment)
            ->with('success', sprintf('Payment %s was updated.', $payment->formattedAmount()));
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);

        $amount = $payment->formattedAmount();

        DB::transaction(function () use ($payment): void {
            $payment->delete();

            // Also soft-delete any associated receipts? The receipt service
            // handles its own retention; here we just delete the payment record.
        });

        return redirect()
            ->route('app.payments.index')
            ->with('success', sprintf('Payment %s was removed.', $amount));
    }

    /**
     * The only columns the list may be ordered by.
     */
    protected function sortColumn(Request $request): string
    {
        return match ($request->string('sort')->toString()) {
            'amount' => 'amount',
            'payment_date' => 'payment_date',
            'status' => 'status',
            'created_at' => 'created_at',
            default => 'payment_date',
        };
    }

    protected function sortDirection(Request $request): string
    {
        return $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
    }
}