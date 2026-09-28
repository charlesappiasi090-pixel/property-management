<?php

namespace Database\Factories;

use App\Enums\PropertyType;
use App\Models\Business;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /**
     * `business_id` is filled by the `BelongsToBusiness` creating hook from the
     * active `BusinessContext`, so a test can drop this factory inside
     * `BusinessContext::runFor()` and get a correctly-owned row. States that
     * need a different tenant set it explicitly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->city();

        return [
            'name' => fake()->unique()->buildingName().' '.fake()->randomElement(['Court', 'House', 'Terrace', 'Mews', 'Lodge']),
            // A weighted choice, not a uniform one, so a 30-row factory run
            // produces a portfolio that looks like a portfolio instead of one
            // containing six `land` parcels.
            'property_type' => fake()->randomElement([
                PropertyType::Apartment,
                PropertyType::Apartment,
                PropertyType::House,
                PropertyType::Townhouse,
                PropertyType::Condo,
                PropertyType::Duplex,
                PropertyType::MixedUse,
            ]),
            'description' => fake()->optional(0.6)->paragraph(),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => null,
            'city' => $city,
            'state' => fake()->stateAbbr(),
            'postal_code' => fake()->postcode(),
            'country' => 'US',
            'owner_name' => fake()->name(),
            'owner_email' => fake()->optional()->safeEmail(),
            'owner_phone' => fake()->optional(0.5)->phoneNumber(),
            'year_built' => fake()->numberBetween(1900, (int) now()->year),
            'is_active' => true,
        ];
    }

    public function forBusiness(Business $business): static
    {
        return $this->state(fn (): array => ['business_id' => $business->getKey()]);
    }

    public function type(PropertyType $type): static
    {
        return $this->state(fn (): array => ['property_type' => $type]);
    }

    /**
     * A whole property, e.g. for the commercial/land case where the units
     * panel is not shown at all.
     */
    public function commercial(): static
    {
        return $this->type(PropertyType::Commercial);
    }

    public function land(): static
    {
        return $this->type(PropertyType::Land);
    }

    /**
     * Archived: still in the database, out of the working portfolio.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * Named deterministically, for tests that assert on the visible name.
     */
    public function named(string $name): static
    {
        return $this->state(fn (): array => ['name' => $name]);
    }
}
