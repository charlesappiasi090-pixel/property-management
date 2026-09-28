<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lease>
 */
class LeaseFactory extends Factory
{
    protected $model = Lease::class;

    /**
     * A lease always has a property, a tenant and a start date.
     * `business_id` and `unit_id` are set by the factory or left null
     * (optional unit).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $property = Property::factory();

        return [
            'property_id' => $property->getKey(),
            'tenant_id' => Tenant::factory()->forBusiness($property->business),
            'start_date' => now()->subMonths(rand(1, 12)),
            'end_date' => rand(0, 1) ? now()->addMonths(rand(6, 24))->copy() : null,
            'monthly_rent' => rand(500, 5000).'00',
            'security_deposit' => rand(500, 2500).'00',
            'status' => rand(0, 1) ? 'active' : 'terminated',
        ];
    }

    public function forProperty(Property $property): static
    {
        return $this->state(fn (): array => [
            'property_id' => $property->getKey(),
            'tenant_id' => Tenant::factory()->forBusiness($property)->getKey(),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => 'active']);
    }

    public function terminated(): static
    {
        return $this->state(fn (): array => ['status' => 'terminated']);
    }

    public function withUnit(Unit $unit): static
    {
        return $this->state(fn (): array => ['unit_id' => $unit->getKey()]);
    }
}