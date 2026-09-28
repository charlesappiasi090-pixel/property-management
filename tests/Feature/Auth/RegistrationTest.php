<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => $this->validPassword(),
            'password_confirmation' => $this->validPassword(),
        ]);

        $this->assertAuthenticated();

        // `onboarding.create`, not a `dashboard` route: a brand-new user has no
        // business yet, so the dashboard would bounce them straight back out
        // through the tenant gate. Onboarding is the only productive next step,
        // which is why `RegisteredUserController::store()` sends them there.
        $response->assertRedirect(route('onboarding.create', absolute: false));
    }

    public function test_registration_records_first_login_metadata(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'first-login@example.com',
            'password' => $this->validPassword(),
            'password_confirmation' => $this->validPassword(),
        ]);

        $user = User::where('email', 'first-login@example.com')->firstOrFail();

        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->last_login_ip);
    }

    public function test_registered_password_is_stored_hashed_and_verifies(): void
    {
        $password = $this->validPassword();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'hash-check@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $user = User::where('email', 'hash-check@example.com')->firstOrFail();

        // Guards the `hashed` cast wiring: the raw value must never be
        // recoverable, and it must still authenticate. A double-hash would
        // pass a "not equal to plaintext" check while silently breaking
        // login, so both halves are asserted.
        $this->assertNotSame($password, $user->password);
        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function test_registration_rejects_a_mismatched_password_confirmation(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'mismatch@example.com',
            'password' => $this->validPassword(),
            'password_confirmation' => 'something-else',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'mismatch@example.com']);
    }

    public function test_registration_rejects_a_password_below_the_policy(): void
    {
        // The policy is intentionally stricter than Laravel's default because
        // this system handles tenancy documents. Asserting the rejection pins
        // that decision so it cannot be quietly relaxed.
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'weak@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'weak@example.com']);
    }
}
