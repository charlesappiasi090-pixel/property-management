<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\BusinessContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clears the tenant at the end of every request.
 *
 * THIS IS NOT OPTIONAL HYGIENE — IT IS A CORRECTNESS REQUIREMENT.
 *
 * `BusinessContext` is a container singleton, so it survives for the lifetime
 * of the PHP process, not just the request. Under `php artisan queue:work` or
 * Octane, one process handles job after job from DIFFERENT businesses. If
 * this middleware were omitted, or registered only on the web group, a worker
 * handling tenant B's job would still carry tenant A's resolved business and
 * the global scope would silently read the wrong landlord's rows.
 *
 * SCOPE OF THIS CLASS — READ THIS BEFORE RELYING ON IT
 * ----------------------------------------------------
 * This is HTTP middleware. It runs for web requests only. It does NOT run for
 * queued jobs or for console commands; global HTTP middleware is never applied
 * to a job. Anything that runs off the HTTP lifecycle must clear the context
 * itself:
 *
 *   - a job: `$context->forget()` in a `finally`, or a job-level middleware
 *     registered on the queue (`Illuminate\Queue\Middleware\...`) plus a
 *     `JobProcessing`/`JobProcessed` listener. See
 *     `App\Providers\QueueServiceProvider` for the listener that covers the
 *     common case.
 *   - a console command: `BusinessContext::runFor()` / `runWithoutScope()`,
 *     both of which restore the previous state in a `finally`.
 *
 * Getting this wrong leaks one tenant's context into the next unit of work, so
 * the listeners are registered rather than left to each job author.
 */
class ForgetActiveBusiness
{
    public function __construct(protected BusinessContext $context) {}

    /**
     * Forget BEFORE the request runs as well as after.
     *
     * Forgetting only afterwards leaves a stale tenant in place for the whole
     * request if a previous unit of work in the same process never cleaned up
     * (a job that threw, a console command that called `set()` directly). A
     * pre-clear makes the context empty by default, so an unresolved request
     * fails closed rather than inheriting someone else's tenant.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->context->forget();

        try {
            return $next($request);
        } finally {
            // Runs on the exception path too. Without the `finally` a thrown
            // exception would skip the clear entirely.
            $this->context->forget();
        }
    }

    /**
     * The `terminate()` hook fires after the response has already been sent
     * to the client, which is the correct place to release request-scoped
     * state in a long-running worker. It is a second line of defence: `handle()`
     * has already cleared by then, but a listener or destructor may have set a
     * tenant between the response and termination.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->context->forget();
    }
}
