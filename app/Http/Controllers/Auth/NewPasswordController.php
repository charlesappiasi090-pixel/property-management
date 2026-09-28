<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        // `$token` and `$email` are passed as top-level variables, not just
        // nested under `request`.
        //
        // The view reads `$token` and `$email` directly. Breeze's own view did
        // the same but received them via a `@php`/`$request->route('token')`
        // shim, which is easy to lose when the view is rewritten. Without this,
        // `Undefined variable $token` is thrown at render time and the whole
        // reset screen is a 500 — which is the only screen a locked-out user can
        // reach.
        //
        // `route('token')` first, so a request that actually arrived via the
        // signed URL is authoritative; the query string is the fallback for a
        // re-render after a validation error, where the route parameter is gone
        // but the form still has to submit a working token.
        return view('auth.reset-password', [
            'token' => $request->route('token') ?? $request->string('token')->toString(),
            'email' => $request->string('email')->toString(),
        ]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                /*
                 * `remember_token` is cleared deliberately.
                 *
                 * A password reset is the recovery path for a compromised
                 * account, so every existing session and every "remember me"
                 * cookie minted with the old credential must stop working.
                 * Rotating the token invalidates all of them; leaving it alone
                 * means an attacker who stole the password keeps their session
                 * even after the owner resets it.
                 *
                 * `forceFill` is required for `remember_token` because it is
                 * guarded, and the `password` value is assigned raw so the
                 * model's `hashed` cast performs the hashing — consistent with
                 * registration and the password-update flow. Calling
                 * `Hash::make()` here as well would be redundant.
                 */
                $user->forceFill([
                    'password' => $request->string('password')->toString(),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
