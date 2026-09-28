<?php

namespace Database\Factories;

use App\Enums\BusinessStatus;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    /**
     * Defaults to an ACTIVE, subscribed, fully populated tenant.
     *
     * ACTIVE rather than TRIALING is the useful default: a trial business is
     * read-only in production, so a factory that produced one would make every
     * write-path test fail for a reason that has nothing to do with the code
     * under test. Tests that care about the trial/billing states override
     * `status` explicitly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'legal_name' => $name.' Ltd',
            'tax_id' => Str::upper(Str::random(3)).'-'.fake()->numerify('########'),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => null,
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'postal_code' => fake()->postcode(),
            'country' => 'US',
            'timezone' => config('propertyhub.timezone'),
            'locale' => config('propertyhub.locale'),
            'currency' => config('propertyhub.currency'),
            'status' => BusinessStatus::ACTIVE,
            'trial_ends_at' => null,
            'settings' => [],
        ];
    }

    /**
     * Inside a trial window, fully writable.
     */
    public function trialing(int $days = 14): static
    {
        return $this->state(fn (): array => [
            'status' => BusinessStatus::TRIALING,
            'trial_ends_at' => now()->addDays($days),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => BusinessStatus::EXPIRED,
            'trial_ends_at' => null,
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn (): array => ['status' => BusinessStatus::PAST_DUE]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => BusinessStatus::SUSPENDED]);
    }
}
