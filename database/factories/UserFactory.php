<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The password every generated user gets, hashed once per process.
     *
     * WHY NOT THE BREEZE DEFAULT OF 'password'
     * ---------------------------------------
     * `AppServiceProvider` tightens `Password::defaults()` to 12+ characters with
     * mixed case, numbers and symbols. A factory that seeded 'password' would
     * produce users who cannot pass their own application's validation, which
     * made several inherited auth tests fail for a reason that had nothing to
     * do with the code under test.
     *
     * The factory uses the raw value and lets the model's `hashed` cast do the
     * hashing, matching how the real registration flow writes the column. That
     * also means `PasswordResetTest` and `PasswordUpdateTest` can reuse this
     * constant as a genuinely known plaintext.
     */
    public const DEFAULT_PASSWORD = 'Correct-Horse-7!';

    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= static::DEFAULT_PASSWORD,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
