<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\JournalRequest;
use App\Models\JournalEntry;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Journal entries – a simple general‑ledger for the business.
 *
 * Journal entries are business‑scoped only (no morph to other models in this
 * phase).  The controller nests create under a "placeholder" property route so
 * the `ResolveActiveBusiness` binder runs first, but the entry itself only
 * records to the `journal_entries` table.
 *
 * No index/list page is provided in Phase 9 – the UI can be added later.
 * Create is the primary action.
 */
class JournalController extends Controller
{
    public function __construct(
        protected BusinessContext $context,
    ) {}

    public function create(): View
    {
        $this->authorize('create', JournalEntry::class);

        return view('backoffice.journals.create');
    }

    public function store(JournalRequest $request): RedirectResponse
    {
        JournalEntry::assertCanCreate($this->context);

        $entry = new JournalEntry([
            ...$request->journalEntryAttributes(),
            'business_id' => app('business')->id, // stamped by BelongsToBusiness creating hook
            'business_type' => app('business')->getMorphClass(),
        ]);

        $entry->save();

        return redirect()
            ->route('app.dashboard')
            ->with('success', 'Journal entry recorded.');
    }

    public function show(JournalEntry $entry): View
    {
        $this->authorize('view', $entry);

        return view('backoffice.journals.show', [
            'entry' => $entry,
        ]);
    }

    public function edit(JournalEntry $entry): View
    {
        $this->authorize('update', $entry);

        return view('backoffice.journals.edit', [
            'entry' => $entry,
        ]);
    }

    public function update(JournalRequest $request, JournalEntry $entry): RedirectResponse
    {
        DB::transaction(function () use ($request, $entry): void {
            JournalEntry::assertCanCreate($this->context);

            $entry->fill($request->journalEntryAttributes())->save();
        });

        return redirect()
            ->route('app.dashboard')
            ->with('success', 'Journal entry updated.');
    }

    public function destroy(JournalEntry $entry): RedirectResponse
    {
        $this->authorize('delete', $entry);

        $description = $entry->description;

        $entry->delete();

        return redirect()
            ->route('app.dashboard')
            ->with('success', sprintf('Journal entry %s was removed.', $description));
    }
}