<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     *
     * The last owner of a business is BLOCKED from deleting their account.
     *
     * Deleting the account removes the only person who can reach the tenant:
     * every other member is a guest of that owner's workspace, so the business,
     * its properties, its staff, and its billing history would all become
     * unreachable through the application. Recovering from that means direct
     * database access, which is exactly the situation support cannot resolve
     * from a ticket.
     *
     * The fix is to transfer ownership or promote another member first. This is
     * a deliberate refusal rather than an oversight, and it applies only to the
     * SOLE owner — a business with several owners can still lose one of them.
     *
     * Errors are reported in the `userDeletion` bag so they attach to the
     * deletion panel specifically, not to the profile form above it.
     *
     * @throws ValidationException
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $businessIds = $user->businesses()->pluck('businesses.id');

        // Count owners per business in ONE grouped query.
        //
        // Two things this deliberately avoids:
        //
        // 1. N+1. `->get()->filter()` on `owners()` runs a query per business.
        // 2. A `whereHas()` on the `owners()` relation. Spatie's team scoping
        //    rewrites the nested subquery's team column to the AMBIENT team id,
        //    which is unset on the guest-capable `auth` route group. The
        //    subquery then filters `business_id IS NULL` and matches only the
        //    global role template — so the guard silently saw zero owners and
        //    let a sole owner delete themselves, stranding the tenant. It
        //    failed open because of a tenancy leak.
        //
        // Counting against the pinned `owners()` relation in raw SQL keeps the
        // answer independent of whatever team happens to be active.
        $ownerCounts = Business::query()
            ->whereIn('id', $businessIds)
            ->withCount('owners')
            ->get()
            ->filter(fn (Business $business): bool => $business->owners_count === 1)
            ->pluck('name');

        if ($ownerCounts->isNotEmpty()) {
            $names = $ownerCounts->implode(', ');

            throw ValidationException::withMessages([
                'password' => sprintf(
                    'Transfer ownership of %s to another member before deleting your account, '
                    .'otherwise nobody would be able to manage it. If you are the only member, '
                    .'add one first.',
                    $names
                ),
            ])->errorBag('userDeletion');
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
