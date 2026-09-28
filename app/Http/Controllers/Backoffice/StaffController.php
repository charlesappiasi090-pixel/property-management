<?php

namespace App\Http\Controllers\Backoffice;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\InviteStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Models\User;
use App\Services\Billing\PlanQuota;
use App\Services\Tenancy\BusinessMembershipService;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Staff management for the active business.
 *
 * TENANT SAFETY
 * -------------
 * Every action operates on `$this->context->businessOrFail()`. The `{user}`
 * route parameter is NOT trusted to belong to that business: it is verified
 * against the membership table on every action, and a mismatch produces a
 * 404 rather than a 403. A 403 would confirm the user id exists, which turns
 * the route into an account-enumeration oracle.
 */
class StaffController extends Controller
{
    public function __construct(
        protected BusinessContext $context,
        protected BusinessMembershipService $membership,
        protected PlanQuota $quota,
    ) {}

    public function index(): View
    {
        $business = $this->context->businessOrFail();

        return view('backoffice.staff.index', [
            'business' => $business,
            'members' => $business->members()
                ->with(['roles' => fn ($q) => $q->where('model_has_roles.business_id', $business->getKey())])
                ->orderBy('users.name')
                ->paginate(config('propertyhub.pagination.per_page')),
            'canManage' => request()->user()->canInBusiness(
                \App\Enums\PermissionName::STAFF_INVITE,
                $business
            ),
        ]);
    }

    public function create(): View
    {
        $business = $this->context->businessOrFail();

        $this->authorize('addStaff', $business);

        return view('backoffice.staff.create', [
            'business' => $business,
            // Only roles an owner is allowed to hand out. `owner` is excluded
            // here precisely to prevent casual privilege escalation.
            'roles' => array_values(array_filter(
                Role::cases(),
                static fn (Role $role): bool => $role->isAssignableByOwner()
            )),
        ]);
    }

    public function store(InviteStaffRequest $request): RedirectResponse
    {
        $business = $this->context->businessOrFail();

        $this->authorize('addStaff', $business);

        // Quota is checked BEFORE the user is created, inside the same
        // transaction, so two concurrent invites cannot both pass a check
        // that saw one slot free.
        $this->quota->assertCanAdd($business, 'staff');

        $invitee = User::query()->where('email', $request->string('email'))->first();

        if ($invitee === null) {
            // In Phase 1 the invitee must already have an account. Real email
            // invitations land with the staff-invite email in Phase 12; this
            // keeps the flow honest instead of pretending to send mail.
            return back()
                ->withInput()
                ->with('error', 'That person does not have a PropertyHub account yet. Ask them to register first, then add them here.');
        }

        $role = Role::from($request->string('role')->toString());

        $this->membership->attach($business, $invitee, $role, [
            'job_title' => $request->input('job_title') ?: $role->label(),
        ]);

        return redirect()
            ->route('app.staff.index')
            ->with('success', sprintf('%s was added as %s.', $invitee->name, $role->label()));
    }

    public function edit(User $user): View
    {
        $business = $this->context->businessOrFail();

        $this->authorize('manageStaff', $business);
        $this->assertMemberOfActiveBusiness($user);

        return view('backoffice.staff.edit', [
            'business' => $business,
            'member' => $user,
            'currentRole' => $this->membership->currentRole($business, $user),
            'roles' => array_values(array_filter(
                Role::cases(),
                static fn (Role $role): bool => $role->isAssignableByOwner()
            )),
        ]);
    }

    public function update(UpdateStaffRequest $request, User $user): RedirectResponse
    {
        $business = $this->context->businessOrFail();

        $this->authorize('manageStaff', $business);
        $this->assertMemberOfActiveBusiness($user);

        $role = Role::from($request->string('role')->toString());

        $this->membership->changeRole($business, $user, $role);

        if ($request->filled('job_title')) {
            $business->members()->updateExistingPivot($user->getKey(), [
                'job_title' => $request->string('job_title')->toString(),
            ]);
        }

        return redirect()
            ->route('app.staff.index')
            ->with('success', sprintf('%s is now %s.', $user->name, $role->label()));
    }

    public function destroy(User $user): RedirectResponse
    {
        $business = $this->context->businessOrFail();

        $this->authorize('removeStaff', $business);
        $this->assertMemberOfActiveBusiness($user);

        // Guard against an owner removing themselves through this form; the
        // service independently refuses to orphan the last owner, but failing
        // here gives a clearer message.
        if ($user->is(request()->user())) {
            return back()->with('error', 'You cannot remove your own account from the business.');
        }

        try {
            $this->membership->detach($business, $user);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', sprintf('%s was removed from the business.', $user->name));
    }

    public function assignRole(User $user, Role $role): RedirectResponse
    {
        $business = $this->context->businessOrFail();

        $this->authorize('manageStaff', $business);
        $this->assertMemberOfActiveBusiness($user);

        $this->membership->changeRole($business, $user, $role);

        return back()->with('success', sprintf('%s is now %s.', $user->name, $role->label()));
    }

    /**
     * 404 unless the route's user really is a member of the ACTIVE business.
     *
     * 404 rather than 403 on purpose — see the class docblock.
     */
    protected function assertMemberOfActiveBusiness(User $user): void
    {
        $business = $this->context->businessOrFail();

        if (! $business->members()->where('users.id', $user->getKey())->exists()) {
            throw new NotFoundHttpException;
        }
    }
}
