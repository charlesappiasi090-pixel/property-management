<?php

namespace Tests;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Disable the Vite manifest requirement for every test.
     *
     * WHY THIS LIVES HERE RATHER THAN IN A LAYOUT
     * -------------------------------------------
     * The layouts call `@vite(...)`, which throws
     * `ViteManifestNotFoundException` when `public/build/manifest.json` is
     * absent. So every test that renders a Blade view fails with a 500 that
     * has nothing to do with the code under test — 13 of them, all auth and
     * profile screens.
     *
     * `withoutVite()` swaps in a stub that emits no tags. The alternative
     * (an `if (app()->environment('testing'))` branch inside each layout)
     * would mean shipping test-only conditionals in production templates,
     * and it would still not help anyone rendering a view from a console
     * command.
     *
     * WHAT THIS DOES NOT COVER
     * ------------------------
     * It does not verify that the asset pipeline works. That is what
     * `npm run build` and a browser check are for, and neither runs here
     * because Node is not installed. Treat "views render" as verified and
     * "assets are emitted" as still unverified.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * A password that satisfies the application's real policy.
     *
     * AppServiceProvider deliberately tightens `Password::defaults()` to 12+
     * characters with mixed case, numbers and symbols, because this system
     * stores tenancy documents and ID numbers. Breeze's stock fixture is the
     * literal string `'password'`, which that policy rejects.
     *
     * That is why several inherited tests were red on arrival: not a broken
     * feature, a stale fixture. It is also the correct outcome — a test that
     * asserted `'password'` was acceptable would have been asserting a weaker
     * policy than the one shipping.
     *
     * Defined once here so the requirement is stated in a single place and
     * tightening the policy later surfaces as one obvious failure.
     */
    protected function validPassword(): string
    {
        return UserFactory::DEFAULT_PASSWORD;
    }
}
