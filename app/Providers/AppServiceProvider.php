<?php

namespace App\Providers;

use App\Services\Audit\AuditLogger;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Per-request tenant. Registered as a singleton, so it is resolved once
        // and shared by the middleware, the global scopes and every service
        // that needs to know "which business is this?".
        //
        // It is a singleton rather than a `scoped()` binding because the queue
        // worker and artisan command contexts must also share one instance
        // that `ForgetActiveBusiness` can reset between jobs.
        $this->app->singleton(BusinessContext::class);

        $this->app->singleton(AuditLogger::class);
    }

    public function boot(): void
    {
        // ------------------------------------------------------------------
        // Guard rails against accidental N+1 queries in development.
        //
        // A tenant-scoped application makes lazy loading especially
        // dangerous: `foreach ($units as $u) $u->property->name` silently
        // runs one extra query PER UNIT. Failing loudly in local/testing
        // keeps that from reaching production.
        // ------------------------------------------------------------------
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // NOTE: `preventAccessingMissingAttributes()` is intentionally NOT
        // enabled. It also fires on every belongsTo/relation resolution where
        // the column is legitimately absent, which produces a flood of false
        // positives in a schema this shape. Lazy-loading protection catches the
        // N+1 class of bug that actually matters here.

        // ------------------------------------------------------------------
        // Password policy.
        //
        // Deliberately stricter than Laravel's default: this system stores
        // tenancy documents and ID numbers, so a compromised staff password
        // exposes landlord portfolios.
        // ------------------------------------------------------------------
        Password::defaults(fn () => Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised());

        // Honour https:// when APP_URL is https, so signed URLs and
        // `route()` output are correct behind a proxy in production.
        if ($this->app->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $this->shareWithViews();
        $this->registerAuthorizationDirectives();
    }

    /**
     * `@canIn(...)` — Blade's `@can`, but naming the business.
     *
     * WHY NOT `@can`
     * -------------
     * `@can('staff.update')` expands to `$user->can(...)`, which asks spatie
     * whether the user holds the permission *in the team id currently loaded*.
     * That is right by coincidence in a web request — `ResolveActiveBusiness`
     * has already set the id — and wrong everywhere it matters: a stale team
     * id left over from a previous request, a cached fragment, a test that did
     * not arm the context, or a queued job.
     *
     * `@canIn` takes the permission and asks about the business that
     * `BusinessContext` resolved for THIS request, so a template can never
     * render a button the route would then reject, and can never hide one the
     * route would allow. It fails closed when there is no active business.
     *
     *     @canIn(\App\Enums\PermissionName::STAFF_UPDATE)
     *         ...
     *     @endcanIn
     *
     * The closing directive is registered too. Blade only auto-generates an
     * `@end...` for its own built-ins, so a custom block directive needs both
     * halves or the `if` is never closed and the template fails to compile.
     *
     * The corresponding question about a *different* business stays in PHP:
     * `canInBusiness($permission, $business)` — a template has no business
     * other than the active one to mean.
     */
    protected function registerAuthorizationDirectives(): void
    {
        Blade::directive('canIn', function (string $expression): string {
            return sprintf(
                '<?php if (app(\App\Support\Tenancy\BusinessContext::class)->business()'
                .' && auth()->user()?->canInBusiness(%s, app(\App\Support\Tenancy\BusinessContext::class)->business())): ?>',
                $expression,
            );
        });

        Blade::directive('endcanIn', fn (): string => '<?php endif; ?>');
    }

    /**
     * Data every Blade view needs, shared once here rather than passed down
     * through a base layout's view composer chain.
     */
    protected function shareWithViews(): void
    {
        \Illuminate\Support\Facades\View::composer('*', function (\Illuminate\View\View $view): void {
            $view->with('appName', config('propertyhub.name'));
        });
    }
}
