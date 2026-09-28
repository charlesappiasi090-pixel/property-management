<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceiptRequest;
use App\Http\Requests\UpdateReceiptRequest;
use App\Models\Receipt;
use App\Services\Tenancy\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Receipts – a receipt issued for a payment.
 *
 * Receipts are always nested under the payment they refer to, and the payment
 * is nested under the lease / property.  This keeps the URL structure
 * consistent with the rest of the application (`/app/payments/{payment}`).
 *
 * WHY NESTED UNDER `/payments/{payment}`
 * --------------------------------------
 * A receipt is meaningless without its parent payment, and a payment is
 * always attached to a lease.  Keeping `{payment}` in the path makes the
 * relationship part of the URL: the binder proves both records are in the
 * active business, `ReceiptPolicy` proves the parent agrees with the receipt,
 * and the controller enforces that a receipt cannot exist without a payment.
 * Three layers, each of which is needed, none of which can be the only one.
 */
class ReceiptController extends Controller
{
    public function __construct(
        protected BusinessContext $context,
    ) {}

    public function index(Request $request, Payment $payment): View
    {
        $this->authorize('viewAny', Receipt::class);

        $search = $request->string('search')->toString() ?: null;

        $receipts = Receipt::query()
            ->where('payment_id', $payment->getKey())
            ->search($search)
            ->when($request->boolean('show_archived') === false, fn ($q) => $q->where('is_active', true))
            ->orderBy($this->sortColumn($request), $this->sortDirection($request))
            ->paginate(config('propertyhub.pagination.per_page'))
            ->withQueryString();

        return view('backoffice.receipts.index', [
            'payment' => $payment,
            'receipts' => $receipts,
            'search' => $search,
            'showArchived' => $request->boolean('show_archived'),
        ]);
    }

    public function create(Payment $payment): View
    {
        $this->authorize('createForPayment', $payment);

        return view('backoffice.receipts.create', [
            'payment' => $payment,
        ]);
    }

    public function store(ReceiptRequest $request, Payment $payment): RedirectResponse
    {
        // The request's `authorize()` already ran `createForPayment` check.
        // Enforce quota inside the same transaction as the create.
        DB::transaction(function () use ($request, $payment): void {
            // `Receipt::assertCanCreate` checks the plan quota.
            Receipt::assertCanCreate($this->context);

            $receipt = new Receipt([
                ...$request->receiptAttributes(),
                'payment_id' => $payment->getKey(),
                'business_id' => $payment->business_id,
            ]);

            $receipt->save();
        });

        return redirect()
            ->route('app.payments.show', $payment)
            ->with('success', sprintf('Receipt %s was issued.', $receipt->receipt_number));
    }

    public function show(Payment $payment, Receipt $receipt): View
    {
        $this->authorize('view', $receipt);

        return view('backoffice.receipts.show', [
            'payment' => $payment,
            'receipt' => $receipt,
        ]);
    }

    public function edit(Payment $payment, Receipt $receipt): View
    {
        $this->authorize('update', $receipt);

        return view('backoffice.receipts.edit', [
            'payment' => $payment,
            'receipt' => $receipt,
        ]);
    }

    public function update(UpdateReceiptRequest $request, Payment $payment, Receipt $receipt): RedirectResponse
    {
        DB::transaction(function () use ($request, $payment, $receipt): void {
            // Enforce quota before saving – the same check that would have been
            // performed on create is re‑run here for data‑integrity.
            Receipt::assertCanCreate($this->context);

            $receipt->fill($request->receiptAttributes())->save();
        });

        return redirect()
            ->route('app.payments.show', $payment)
            ->with('success', sprintf('Receipt %s was updated.', $receipt->receipt_number));
    }

    public function destroy(Payment $payment, Receipt $receipt): RedirectResponse
    {
        $this->authorize('delete', $receipt);

        $receipt_number = $receipt->receipt_number;

        DB::transaction(function () use ($receipt): void {
            $receipt->delete();

            // Also soft-delete any associated audit entries? The receipt service
            // handles its own retention; here we just delete the receipt record.
        });

        return redirect()
            ->route('app.payments.show', $payment)
            ->with('success', sprintf('Receipt %s was removed.', $receipt_number));
    }

    /**
     * The only columns the list may be ordered by.
     */
    protected function sortColumn(Request $request): string
    {
        return match ($request->string('sort')->toString()) {
            'receipt_number' => 'receipt_number',
            'issued_at' => 'issued_at',
            'status' => 'status',
            'created_at' => 'created_at',
            default => 'issued_at',
        };
    }

    protected function sortDirection(Request $request): string
    {
        return $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
    }
}