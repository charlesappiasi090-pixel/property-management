<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * A mid-tier, public, active plan with finite quotas.
     *
     * Finite rather than unlimited quotas are the interesting default: an
     * unlimited plan makes quota-enforcement tests pass for the wrong reason.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->word().' plan',
            'tagline' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 9, 99),
            'currency' => config('propertyhub.currency'),
            'billing_interval' => 'monthly',
            'trial_days' => 14,
            'max_properties' => 10,
            'max_units' => 50,
            'max_staff' => 3,
            'max_documents' => 100,
            'features' => ['reports' => true, 'exports' => true],
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 0,
        ];
    }

    public function withNoTrial(): static
    {
        return $this->state(fn (): array => ['trial_days' => 0]);
    }

    public function yearly(): static
    {
        return $this->state(fn (): array => [
            'billing_interval' => 'yearly',
            'price' => fake()->randomFloat(2, 99, 999),
        ]);
    }

    public function unlimited(): static
    {
        return $this->state(fn (): array => [
            'max_properties' => null,
            'max_units' => null,
            'max_staff' => null,
            'max_documents' => null,
        ]);
    }

    /**
     * A plan that exists but must never be offered to a customer: retired, or
     * kept for existing subscribers only.
     */
    public function hidden(): static
    {
        return $this->state(fn (): array => ['is_public' => false]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
