<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * Two defaults here are deliberate rather than cosmetic:
     *
     * - `bedrooms` and `bathrooms` are set. A factory that produced nulls would
     *   make `Unit::specification()` return its empty branch, so the assertion
     *   that a studio reads "Studio · 1 bath" and not "0 beds" would never be
     *   exercised by a randomly generated unit.
     * - `property_id` is REQUIRED. A unit with no property is not a state the
     *   application can reach — `PropertyCatalog` always supplies one — and a
     *   factory that could produce it would let a test pass against a row the
     *   app itself would never create.
     *
     * `business_id` comes from the `BelongsToBusiness` creating hook (the
     * active context). It is never set here, so a unit can never be built
     * pointing at a property in a different tenant.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'label' => fake()->unique()->numerify('Unit ##'),
            'bedrooms' => fake()->numberBetween(0, 4),
            // Half baths are ordinary, so this is decimal rather than an int.
            'bathrooms' => fake()->randomElement(['1.0', '1.5', '2.0', '2.5', '3.0']),
            'square_feet' => fake()->numberBetween(400, 2400),
            'floor' => (string) fake()->numberBetween(0, 12),
            'monthly_rent' => fake()->numberBetween(60, 450).'00',
            'notes' => fake()->optional(0.3)->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * All of these units belong to one property, which is also their tenant.
     */
    public function forProperty(Property $property): static
    {
        return $this->state(fn (): array => [
            'property_id' => $property->getKey(),
            'business_id' => $property->business_id,
        ]);
    }

    public function forBusiness(Business $business): static
    {
        return $this->state(fn (): array => ['business_id' => $business->getKey()]);
    }

    public function labeled(string $label): static
    {
        return $this->state(fn (): array => ['label' => $label]);
    }

    public function studio(): static
    {
        return $this->state(fn (): array => [
            'bedrooms' => 0,
            'bathrooms' => '1.0',
            'label' => 'Studio',
        ]);
    }

    /**
     * Awaiting refurbishment: real, but not currently being let.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * No asking rent recorded yet.
     */
    public function withoutRent(): static
    {
        return $this->state(fn (): array => ['monthly_rent' => null]);
    }
}
