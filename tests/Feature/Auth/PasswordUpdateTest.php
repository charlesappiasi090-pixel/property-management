<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => $this->validPassword(),
                'password' => $this->newPassword(),
                'password_confirmation' => $this->newPassword(),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        // The new password works and the old one does not. Asserting only the
        // first half would pass even if the old credential kept working.
        $this->assertTrue(Hash::check($this->newPassword(), $user->password));
        $this->assertFalse(Hash::check($this->validPassword(), $user->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => $this->newPassword(),
                'password_confirmation' => $this->newPassword(),
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');

        // A failed attempt must leave the original credential intact.
        $this->assertTrue(Hash::check($this->validPassword(), $user->refresh()->password));
    }

    public function test_new_password_below_the_policy_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => $this->validPassword(),
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->assertTrue(Hash::check($this->validPassword(), $user->refresh()->password));
    }

    public function test_guests_cannot_update_a_password(): void
    {
        $user = User::factory()->create();

        $response = $this->put('/password', [
            'current_password' => $this->validPassword(),
            'password' => $this->newPassword(),
            'password_confirmation' => $this->newPassword(),
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
        $this->assertTrue(Hash::check($this->validPassword(), $user->refresh()->password));
    }

    private function newPassword(): string
    {
        return 'Sturdy-Glass-9?';
    }
}
