<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * A tenant always belongs to the active business, set by the
     * `BelongsToBusiness` creating hook from `BusinessContext`.  Tests that
     * need a different tenant wrap the factory in
     * `BusinessContext::runFor()`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique->company(),
            'contact_name' => fake->name(),
            'email' => fake->optional()->safeEmail(),
            'phone' => fake->optional(0.5)->phoneNumber(),
            'address_line1' => fake->streetAddress(),
            'address_line2' => null,
            'city' => fake->city(),
            'state' => fake->stateAbbr(),
            'postal_code' => fake->postcode(),
            'country' => 'US',
            'is_active' => true,
        ];
    }

    public function forBusiness($business): static
    {
        return $this->state(fn (): array => ['business_id' => $business->getKey()]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function named(string $name): static
    {
        return $this->state(fn (): array => ['name' => $name]);
    }
}