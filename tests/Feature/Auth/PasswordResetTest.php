<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                // A DIFFERENT value from the current one on purpose. Resetting to
                // the password you already have is not a real reset, and the
                // only way to catch a no-op in the handler is to change it.
                'password' => $this->newPassword(),
                'password_confirmation' => $this->newPassword(),
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_reset_password_replaces_the_stored_hash(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $originalHash = $user->password;

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $originalHash) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => $this->newPassword(),
                'password_confirmation' => $this->newPassword(),
            ]);

            $user->refresh();

            // Both halves matter: the hash must have CHANGED (the reset actually
            // wrote), and the new plaintext must VERIFY (the `hashed` cast did
            // not double-hash it into something unusable).
            $this->assertNotSame($originalHash, $user->password);
            $this->assertTrue(Hash::check($this->newPassword(), $user->password));
            $this->assertFalse(Hash::check($this->validPassword(), $user->password));

            return true;
        });
    }

    public function test_reset_password_rejects_a_password_below_the_policy(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response->assertSessionHasErrors('password');

            // A rejected reset must leave the existing credential working. If
            // validation failed AFTER the write, the user would be locked out of
            // their own account by a form they cannot submit.
            $this->assertTrue(Hash::check($this->validPassword(), $user->fresh()->password));

            return true;
        });
    }

    /**
     * A replacement password that differs from the factory default and still
     * satisfies the application policy.
     */
    private function newPassword(): string
    {
        return 'Sturdy-Glass-9?';
    }
}
