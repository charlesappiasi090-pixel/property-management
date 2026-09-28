<?php

namespace Tests\Feature;

use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public landing page at `/`.
 *
 * This replaces the stock `ExampleTest`, which asserted only that `/` returned
 * 200 without migrating anything. That assertion was cheap but it could not
 * fail for any interesting reason - and it did fail the moment the page started
 * reading real data, because `plans` did not exist in the test database.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The landing page is public and unauthenticated, so it must render
        // without a Vite manifest. `withoutVite()` is the same switch the rest
        // of the suite uses until `npm run build` has been run.
        $this->withoutVite();
    }

    public function test_guests_get_the_marketing_page(): void
    {
        $this->seed(PlanSeeder::class);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('marketing.landing');
        $response->assertSee('Every property, every team, every tenant', escape: false);
    }

    public function test_it_shows_the_seeded_plans_rather_than_hardcoded_ones(): void
    {
        $this->seed(PlanSeeder::class);

        $response = $this->get('/');

        // Prices and quotas come from the catalogue, so this asserts the page is
        // wired to the data. If a plan is renamed or repriced, the marketing
        // page follows automatically instead of quietly lying.
        $response->assertSee('Starter');
        $response->assertSee('Professional');
        $response->assertSee('Business');
        $response->assertSee('$19.00');
        $response->assertSee('$49.00');
        $response->assertSee('$149.00');

        $this->assertCount(3, Plan::query()->publiclyVisible()->get());
    }

    public function test_inactive_or_private_plans_are_not_advertised(): void
    {
        $this->seed(PlanSeeder::class);

        Plan::query()->where('code', 'business')->update(['is_public' => false]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Business</h3>');
        $response->assertSee('Starter');
    }

    public function test_it_advertises_the_roles_the_application_actually_enforces(): void
    {
        $this->seed(PlanSeeder::class);

        $response = $this->get('/');

        foreach (\App\Enums\Role::cases() as $role) {
            $response->assertSee($role->label());
        }
    }

    public function test_it_does_not_leak_the_stock_laravel_welcome_page(): void
    {
        $this->seed(PlanSeeder::class);

        $response = $this->get('/');

        $response->assertDontSee('Laravel has an incredibly rich ecosystem');
        $response->assertDontSee('Laracasts');
        $response->assertDontSee('cloud.laravel.com');
    }
}
