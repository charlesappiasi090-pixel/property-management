<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'business_id' => Business::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::ACTIVE,
            'provider' => 'manual',
            'provider_customer_id' => null,
            'provider_subscription_id' => null,
            'provider_plan_id' => null,
            'trial_ends_at' => null,
            'current_period_start' => $start,
            'current_period_end' => $start->copy()->addMonth(),
            'canceled_at' => null,
            'ended_at' => null,
            'quantity' => 1,
            'meta' => [],
        ];
    }

    public function forPlan(Plan $plan): static
    {
        return $this->state(fn (): array => ['plan_id' => $plan->getKey()]);
    }

    public function forBusiness(Business $business): static
    {
        return $this->state(fn (): array => ['business_id' => $business->getKey()]);
    }

    public function trialing(int $days = 14): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::TRIALING,
            'trial_ends_at' => now()->addDays($days),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::EXPIRED,
            'current_period_end' => now()->subDay(),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::CANCELED,
            'canceled_at' => now(),
        ]);
    }
}
