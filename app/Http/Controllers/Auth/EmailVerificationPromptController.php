<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        return $request->user()->hasVerifiedEmail()
                    ? redirect()->intended(route('home'))
                    // `$email` is passed explicitly. The view displays the
                    // address the link went to, and reading it from the view
                    // via `$user->email` or `auth()->user()` would couple a
                    // template to the auth guard. `auth()->user()` in a Blade
                    // file also keeps working in a queue-rendered or console
                    // context, where there is no authenticated user at all.
                    : view('auth.verify-email', [
                        'email' => $request->user()->email,
                    ]);
    }
}
