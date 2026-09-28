<?php

use App\Http\Middleware\EnsureBackOfficeAccess;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSubscriptionIsActive;
use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\ForgetActiveBusiness;
use App\Http\Middleware\ResolveActiveBusiness;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ------------------------------------------------------------------
        // Middleware ALIASES
        //
        // Aliased so route definitions read as intent
        // (`->middleware('can:leases.update')`) rather than as class names,
        // and so a middleware can be swapped without touching 40 routes.
        // ------------------------------------------------------------------
        $middleware->alias([
            'role' => EnsureRole::class,
            'permission' => EnsurePermission::class,
            'subscription.active' => EnsureSubscriptionIsActive::class,
            'business' => EnsureBackOfficeAccess::class,
            'tenant' => EnsureTenantAccess::class,
        ]);

        // ------------------------------------------------------------------
        // TENANCY MIDDLEWARE ORDER — THIS IS LOAD-BEARING
        //
        // ResolveActiveBusiness must run AFTER authentication (it needs the
        // user) and AFTER session start (it reads/writes `business_id`).
        //
        // It must ALSO run BEFORE SubstituteBindings, and that second
        // constraint is not a detail. SubstituteBindings is what turns
        // `{property}` or `{unit}` in a route into a model, and it resolves
        // them through the `BelongsToBusiness` global scope. With no tenant in
        // BusinessContext that scope applies `where 1 = 0` - it fails CLOSED by
        // design - so every property and unit page would 404 for every user,
        // including the owner of the property. Appending to the end of the
        // group, which is where it used to sit, put it after the binder.
        //
        // So the binder is removed and re-added at the end, behind tenancy:
        //
        //   web: EncryptCookies, AddQueuedCookies, StartSession,
        //        ShareErrors, ValidateCsrfToken, ResolveActiveBusiness,
        //        SubstituteBindings
        //
        // `remove` is applied before `append` by Illuminate's configuration
        // object, and both pass through array_unique(), so SubstituteBindings
        // still appears exactly once.
        //
        // ForgetActiveBusiness is registered as a GLOBAL prepend so it wraps
        // EVERY request including console-invoked ones and, crucially, the
        // queue worker. Leaving tenant state behind between jobs is the
        // single nastiest multi-tenant bug there is.
        // ------------------------------------------------------------------
        $middleware->web(
            remove: [SubstituteBindings::class],
            append: [
                ResolveActiveBusiness::class,
                SubstituteBindings::class,
            ],
        );

        $middleware->prepend(ForgetActiveBusiness::class);

        // ------------------------------------------------------------------
        // Redirect guests to the login screen, honouring the "intended" URL
        // so a user deep-links to a report and lands there after signing in.
        // ------------------------------------------------------------------
        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // ------------------------------------------------------------------
        // No active business is an authorisation failure, not a server error.
        // Without this the user sees a 500 stack trace in development and a
        // blank error page in production.
        // ------------------------------------------------------------------
        $exceptions->render(function (\App\Exceptions\NoActiveBusinessException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 403);
            }

            return redirect()
                ->route('login')
                ->withErrors(['business' => $e->getMessage()]);
        });

        // Quota problems are a normal business outcome -> 422 with a message
        // the UI shows verbatim, not a 500.
        $exceptions->render(function (\App\Services\Billing\QuotaExceededException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        });

        // Plan-gated capability -> 403 with an upgrade prompt.
        $exceptions->render(function (\App\Services\Billing\FeatureNotAvailableException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 403);
            }

            return back()->with('error', $e->getMessage());
        });
    })->create();
