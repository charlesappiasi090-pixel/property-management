<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        // The `hashed` cast on the model performs the hashing, so the plain
        // value is assigned here. Calling `Hash::make()` as well would be
        // redundant and would spread one responsibility across two places.
        $request->user()->password = $validated['password'];
        $request->user()->save();

        return back()->with('status', 'password-updated');
    }
}
