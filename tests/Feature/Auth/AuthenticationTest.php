<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            // The plaintext behind `UserFactory`'s default state, which is
            // deliberately policy-compliant. See `UserFactory::DEFAULT_PASSWORD`.
            'password' => $this->validPassword(),
        ]);

        $this->assertAuthenticated();

        // `home`, not `dashboard`. `AuthenticatedSessionController::store()`
        // falls back to `route('home')`, and `home` is state-aware: it sends a
        // user with no business to onboarding and a tenant-only user to the
        // portal. Asserting the state-aware entry point is what keeps this
        // test honest about where a real login actually lands.
        $response->assertRedirect(route('home', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
