<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Services\Tenancy\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

/**
 * Expenses – a cost incurred by a business.
 *
 * EXPENSES ARE NESTED UNDER THE PROPERTY THAT INCURRED THEM.
 * -------------------------------------------------------
 * An expense is always associated with a property (and optionally a lease,
 * unit, or tenant).  Keeping `{property}` in the path makes the relationship
 * part of the URL: the binder proves both records are in the active business,
 * `ExpensePolicy` proves the parent agrees with the expense, and the controller
 * enforces that an expense cannot exist without a property.  Three layers,
 * each of which is needed, none of which can be the only one.
 *
 * No expenses index page: an expense has no meaning without its address, so
 * it is managed on the property page.  Portfolio‑wide expense reporting is
 * the "spending summary" in Phase 7, not a second CRUD screen.
 */
class ExpenseController extends Controller
{
    public function __construct(
        protected BusinessContext $context,
    ) {}

    public function index(Request $request, Property $property): View
    {
        $this->authorize('viewAny', Expense::class);

        $search = $request->string('search')->toString() ?: null;

        $expenses = Expense::query()
            ->where('property_id', $property->getKey())
            ->search($search)
            ->when($request->boolean('show_archived') === false, fn ($q) => $q->where('is_active', true))
            ->with(['property', 'tenant'])
            ->orderBy($this->sortColumn($request), $this->sortDirection($request))
            ->paginate(config('propertyhub.pagination.per_page'))
            ->withQueryString();

        return view('backoffice.expenses.index', [
            'property' => $property,
            'expenses' => $expenses,
            'search' => $search,
            'showArchived' => $request->boolean('show_archived'),
            'expenseCategories' => ExpenseCategory::cases(),
            'quota' => [
                'used' => $this->context->business()?->expenses()->count() ?? 0,
                'limit' => null, // expenses may not have a plan limit in Phase 5
            ],
        ]);
    }

    public function create(Property $property): View
    {
        $this->authorize('createForProperty', $property);

        return view('backoffice.expenses.create', [
            'property' => $property,
            'categories' => ExpenseCategory::cases(),
        ]);
    }

    public function store(ExpenseRequest $request, Property $property): RedirectResponse
    {
        // The request's `authorize()` already ran `createForProperty` check.
        // Enforce quota inside the same transaction as the create.
        DB::transaction(function () use ($request, $property): void {
            // `Expense::assertCanCreate` checks the plan quota.
            Expense::assertCanCreate($this->context);

            $expense = new Expense([
                ...$request->expenseAttributes(),
                'property_id' => $property->getKey(),
                'business_id' => $property->business_id, // stamped by BelongsToBusiness creating hook
            ]);

            $expense->save();
        });

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', sprintf('Expense %s was recorded.', $request->expenseAttributes()['category']??'other'));
    }

    public function show(Property $property, Expense $expense): View
    {
        $this->authorize('view', $expense);

        return view('backoffice.expenses.show', [
            'property' => $property,
            'expense' => $expense,
        ]);
    }

    public function edit(Property $property, Expense $expense): View
    {
        $this->authorize('update', $expense);

        return view('backoffice.expenses.edit', [
            'property' => $property,
            'expense' => $expense,
            'categories' => ExpenseCategory::cases(),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Property $property, Expense $expense): RedirectResponse
    {
        DB::transaction(function () use ($request, $property, $expense): void {
            // Enforce quota before saving – the same check that would have been
            // performed on create is re‑run here for data‑integrity.
            Expense::assertCanCreate($this->context);

            $expense->fill($request->expenseAttributes())->save();
        });

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', sprintf('Expense %s was updated.', $expense->categoryLabel()));
    }

    public function destroy(Property $property, Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $category = $expense->categoryLabel();

        DB::transaction(function () use ($expense): void {
            $expense->delete();

            // Also soft-delete any associated audit entries? The expense service
            // handles its own retention; here we just delete the expense record.
        });

        return redirect()
            ->route('app.properties.show', $property)
            ->with('success', sprintf('Expense %s was removed.', $category));
    }

    /**
     * The only columns the list may be ordered by.
     */
    protected function sortColumn(Request $request): string
    {
        return match ($request->string('sort')->toString()) {
            'category' => 'category',
            'expense_date' => 'expense_date',
            'status' => 'status',
            'created_at' => 'created_at',
            default => 'expense_date',
        };
    }

    protected function sortDirection(Request $request): string
    {
        return $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
    }
}