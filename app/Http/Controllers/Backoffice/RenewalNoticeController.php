<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\RenewalNoticeRequest;
use App\Models\Lease;
use App\Models\RenewalNotice;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lease renewal notices – sent to tenants when their lease is approaching
 * expiry (default 90 days).  Notices are business‑scoped and always nested
 * under the lease they concern.
 *
 * Invariants
 * ---------
 * – Every notice is attached to the active business via `BelongsToBusiness`.
 * – Cross‑tenant access returns 404, never 403 (RenewalNoticePolicy).
 * – Quota is enforced before INSERT via `RenewalNotice::assertCanCreate()`.
 * – The UI can filter by read/unread and by days-until-expiry.
 */
class RenewalNoticeController extends Controller
{
    public function __construct(
        protected BusinessContext $context,
    ) {}

    /**
     * List renewal notices for a lease.
     */
    public function index(Lease $lease): View
    {
        $this->authorize('view', $lease);

        $notices = RenewalNotice::business()
            ->where('lease_id', $lease->getKey())
            ->orderBy('sent_at', 'desc')
            ->paginate(config('propertyhub.pagination.per_page'));

        return view('backoffice.renewal_notices.index', [
            'lease' => $lease,
            'notices' => $notices,
        ]);
    }

    /**
     * Show the compose form for a lease.
     */
    public function create(Lease $lease): View
    {
        $this->authorize('create', $renewalNotice = new RenewalNotice());

        return view('backoffice.renewal_notices.create', [
            'lease' => $lease,
            'notice' => $renewalNotice,
            'sendAtOptions' => [
                'now' => 'Now',
                '3_days' => '3 days',
                '7_days' => '7 days',
                '14_days' => '14 days',
                '30_days' => '30 days',
            ],
        ]);
    }

    /**
     * Store a new renewal notice.
     */
    public function store(RenewalNoticeRequest $request, Lease $lease): RedirectResponse
    {
        RenewalNotice::assertCanCreate($this->context);

        $data = $request->renewalNoticeAttributes();
        $data['lease_id'] = $lease->getKey();
        $data['business_id'] = app('business')->id; // stamped by BelongsToBusiness
        $data['business_type'] = app('business')->getMorphClass();

        $notice = RenewalNotice::create($data);

        return redirect()
            ->route('app.leases.show', $lease)
            ->with('success', 'Renewal notice sent to tenant.');
    }

    /**
     * Mark a notice as read (or unread).
     */
    public function readToggle(RenewalNotice $notice): RedirectResponse
    {
        $this->authorize('view', $notice);

        // Toggle read status.
        if ($notice->read_at) {
            $notice->read_at = null;
        } else {
            $notice->read_at = now();
        }

        $notice->save();

        return back()->with('success', sprintf('Notice %s marked %s.', $notice->id, $notice->isUnread() ? 'unread' : 'read'));
    }

    /**
     * Remove a notice.
     */
    public function destroy(RenewalNotice $notice): RedirectResponse
    {
        $this->authorize('delete', $notice);

        $subject = $notice->subject;

        $notice->delete();

        return back()->with('success', sprintf('Notice %s was removed.', $subject));
    }
}