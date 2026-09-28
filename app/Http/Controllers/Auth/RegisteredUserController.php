<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * WHAT CHANGED FROM THE BREEZE DEFAULT, AND WHY
     * ---------------------------------------------
     * 1. Redirects to `onboarding.create`, not `dashboard`. A brand-new user
     *    has no business, so `/app` would bounce them straight back out via
     *    the `business` gate. Onboarding is the only productive next step.
     *
     * 2. Accepts an optional `phone`, because the staff list and maintenance
     *    dispatch both need it and asking twice is worse UX.
     *
     * 3. Records `last_login_at`/`last_login_ip`, which the staff list shows.
     *    These are assigned explicitly on the model, never mass-assigned from
     *    the request — see the note in `store()`.
     *
     * 4. `login_at` is set here rather than in the session controller so the
     *    value is correct even for OAuth/social sign-in added later.
     *
     * 5. `MustVerifyEmail` is still NOT enabled on the User model. Forcing
     *    verification before a user can create their tenant means a broken
     *    mail configuration locks people out of their own new account. It is
     *    switched on in the phase where the mailer is verified end to end.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'password.confirmed' => 'The two passwords you entered do not match.',
        ], [
            'name' => 'full name',
            'phone' => 'phone number',
        ]);

        $user = new User;

        // Assigned explicitly rather than through `User::create([...])`.
        //
        // `last_login_at` / `last_login_ip` are deliberately absent from
        // `$fillable`: they are audit data about a session, not user-supplied
        // profile data, and a fillable column can be written by whatever the
        // client posts. Setting them here keeps them server-controlled.
        //
        // Passing them inside the `create()` payload also threw, because
        // `AppServiceProvider` enables `preventSilentlyDiscardingAttributes()`
        // outside production — a non-fillable key is a MassAssignmentException,
        // so registration returned a 500 for every new signup.
        $user->fill([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'phone' => $request->filled('phone') ? $request->string('phone')->toString() : null,
        ]);

        // The `hashed` cast on the model hashes this for us. Calling
        // `Hash::make()` here as well would be redundant (the cast detects an
        // already-hashed value and leaves it alone) and would leave two places
        // responsible for one behaviour.
        $user->password = $request->string('password')->toString();

        $user->last_login_at = now();
        $user->last_login_ip = $request->ip();

        $user->save();

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('onboarding.create');
    }
}
