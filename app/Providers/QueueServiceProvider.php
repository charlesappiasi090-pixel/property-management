<?php

namespace App\Providers;

use App\Support\Tenancy\BusinessContext;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Keeps `BusinessContext` from leaking between units of work on the queue.
 *
 * WHY THIS EXISTS
 * ---------------
 * `BusinessContext` is a container SINGLETON. In a web request that is safe
 * because `ForgetActiveBusiness` middleware tears it down at the end of the
 * request. In a long-lived worker there is no such middleware: global HTTP
 * middleware is never applied to a job.
 *
 * So without this provider, `php artisan queue:work` looks like this:
 *
 *   job 1  -> PropertyCreated, tenant 41   (sets context to 41)
 *   job 2  -> RentReminder,     tenant 87   (sets context to 87)
 *
 * That happens to be fine, but the failure mode that bites is the job that
 * does NOT set a context of its own — a retry of job 1 that resumes after
 * job 2 ran, or any job that only reads. It inherits 87, and the
 * `BelongsToBusiness` global scope silently filters to tenant 87's rows. The
 * job then processes the WRONG LANDLORD'S data and, because the global scope
 * is designed to be invisible, nothing raises an error.
 *
 * The fix is to clear the context on BOTH edges of every job:
 *   - before  : no job ever starts inside another job's tenant
 *   - after   : no job leaves its tenant behind for the next one
 *
 * `JobProcessing` covers "before" for the happy path; `JobExceptionOccurred`
 * and `JobFailed` cover the paths where the job threw and never reached the
 * "after" hook.
 */
class QueueServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $forget = function (): void {
            $this->app->make(BusinessContext::class)->forget();
        };

        // Before the job runs. Idempotent and cheap.
        Event::listen(JobProcessing::class, $forget);

        // After a successful job.
        Event::listen(JobProcessed::class, $forget);

        // After a job that threw. Without these two, a failed job is the
        // single most likely way to leave a stale tenant in place.
        Event::listen(JobExceptionOccurred::class, $forget);
        Event::listen(JobFailed::class, $forget);
    }
}
