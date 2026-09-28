<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Business;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Single write path for the audit trail.
 *
 * WHY A SERVICE INSTEAD OF A MODEL OBSERVER
 * ----------------------------------------
 * An Eloquent observer would be tempting — it catches every write with no
 * extra code. It is the wrong tool here:
 *
 *   - Observers cannot distinguish "user edited the name" from "a scheduled
 *     job updated the trial date". Audit entries need an intent and an actor.
 *   - Observers fire inside the model lifecycle, so a rolled-back transaction
 *     would already have written a log row that then does not exist.
 *   - Observers make it easy to *forget* to disable one on a hot path.
 *
 * Here the caller states the event explicitly (`$this->audit->record(...,
 * AuditEvent::LeaseCreated, ...)`), the actor is unambiguous, and writes can
 * participate in the caller's transaction.
 *
 * @see \App\Enums\AuditEvent for the canonical event names.
 */
class AuditLogger
{
    /**
     * Extra fields registered via `pushContext()` for the duration of one
     * request, so a service deep in a call stack does not have to thread the
     * request through.
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    public function __construct(
        protected BusinessContext $businessContext,
        protected ?Request $request = null,
    ) {
        $this->request ??= request();
    }

    /**
     * Record a change to an existing record.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<int, string>  $dirtyFields  Restrict the snapshot to these fields; omit for all.
     */
    public function record(
        Model $subject,
        string $event,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        array $dirtyFields = [],
    ): AuditLog {
        return $this->write(
            businessId: $this->resolveBusinessId($subject),
            userId: $this->resolveUserId(),
            subject: $subject,
            event: $event,
            oldValues: $this->filter($oldValues, $dirtyFields),
            newValues: $this->filter($newValues, $dirtyFields),
            description: $description,
        );
    }

    /**
     * Record an event with no model subject — a login, a failed permission
     * check, a document download, a subscription change.
     */
    public function event(
        string $event,
        string $description,
        ?Model $subject = null,
        ?array $context = null,
    ): AuditLog {
        return $this->write(
            // Resolved from the subject first — see `resolveBusinessId()`.
            // Relying on the context alone lost the tenant on every "about
            // this business" event, because those are precisely the ones
            // written before a context exists.
            businessId: $this->resolveBusinessId($subject),
            userId: $this->resolveUserId(),
            subject: $subject,
            event: $event,
            oldValues: null,
            newValues: $context,
            description: $description,
        );
    }

    /**
     * Diff a model's original and current attributes automatically.
     *
     * Call immediately after saving, using `getOriginal()` which reflects the
     * database state as of the model's load/save cycle.
     *
     * @param  array<int, string>|null  $fields
     * @return AuditLog|null  The written row, or NULL when nothing changed.
     */
    public function recordChanges(
        Model $subject,
        string $event,
        ?string $description = null,
        ?array $fields = null,
    ): ?AuditLog {
        $original = $subject->getOriginal();
        $current = $subject->getAttributes();

        $watch = $fields ?? array_keys($current);

        $old = [];
        $new = [];

        foreach ($watch as $field) {
            // Skip fields the model does not actually have (e.g. a partial
            // select) and internal bookkeeping.
            if (str_contains($field, 'password') || str_contains($field, 'remember_token')) {
                continue;
            }

            $before = $original[$field] ?? null;
            $after = $current[$field] ?? null;

            if ($before === $after) {
                continue;
            }

            $old[$field] = $before;
            $new[$field] = $after;
        }

        /*
         * Nothing actually changed: do not pollute the log with no-ops.
         *
         * Returns NULL, not a bare `new AuditLog`. An unsaved model returned
         * from a method typed `: AuditLog` is indistinguishable from a real
         * row to the caller — `$log->exists` would be false, `id` null, and
         * any code that assumed the write happened would be silently wrong. A
         * nullable return makes "no row was written" explicit in the type.
         */
        if ($old === [] && $new === []) {
            return null;
        }

        return $this->record(
            $subject,
            $event,
            $old,
            $new,
            $description,
        );
    }

    /**
     * Attach extra context (e.g. a correlation id from a queued job) to every
     * subsequent write in this request.
     *
     * @param  array<string, mixed>  $context
     */
    public function pushContext(array $context): void
    {
        $this->context = [...$this->context, ...$context];
    }

    /**
     * Correlate every audit row written during this request.
     */
    public function beginRequestBatch(?string $requestId = null): string
    {
        $requestId ??= (string) Str::uuid();

        $this->pushContext(['request_id' => $requestId]);

        return $requestId;
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>|null  $values
     * @param  array<int, string>  $fields
     * @return array<string, mixed>|null
     */
    protected function filter(?array $values, array $fields): ?array
    {
        if ($values === null) {
            return null;
        }

        return $fields === [] ? $values : array_intersect_key($values, array_flip($fields));
    }

    /**
     * Which tenant does this entry belong to?
     *
     * Three sources, in order of reliability:
     *
     *  1. The subject IS the tenant (`Business`). Its own key is the answer.
     *  2. The subject is a tenant-scoped model and carries `business_id`.
     *  3. The active context, as a last resort.
     *
     * (1) is not a special case for tidiness. Every "about this business"
     * event — starting a trial, adding a member — happens while that business
     * is being created, before any middleware has resolved a context, so the
     * context is empty exactly then and the row used to be written with a
     * NULL `business_id`: invisible to `$business->auditLogs()` and
     * attributable to nobody.
     *
     * (2) is what makes an owner managing several workspaces unambiguous: the
     * log must say which tenant the record belongs to, not merely which
     * tenant the request happened to be scoped to.
     */
    protected function resolveBusinessId(?Model $subject = null): ?int
    {
        if ($subject instanceof Business) {
            return $subject->getKey() === null ? null : (int) $subject->getKey();
        }

        $fromSubject = $subject?->getAttribute('business_id');

        if ($fromSubject !== null) {
            return (int) $fromSubject;
        }

        return $this->businessContext->id();
    }

    protected function resolveUserId(): ?int
    {
        $user = $this->request?->user();

        // In a console context `$this->request` may be a real but
        // unauthenticated Request; fall back to the container guard.
        if ($user === null) {
            $user = auth()->user();
        }

        return $user?->getKey();
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    protected function write(
        ?int $businessId,
        ?int $userId,
        ?Model $subject,
        string $event,
        ?array $oldValues,
        ?array $newValues,
        ?string $description,
    ): AuditLog {
        $user = $this->request?->user() ?? auth()->user();

        return AuditLog::query()->create([
            'business_id' => $businessId,
            'user_id' => $userId,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'event' => $event,
            'description' => $description === null ? null : Str::limit($description, 500, ''),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $this->request?->ip(),
            'user_agent' => $this->request?->userAgent() === null
                ? null
                : Str::limit((string) $this->request->userAgent(), 500, ''),
            'url' => $this->request?->fullUrl() === null
                ? null
                : Str::limit((string) $this->request->fullUrl(), 500, ''),
            'method' => $this->request?->method(),
            'request_id' => $this->context['request_id'] ?? null,
            'route_name' => $this->request?->route()?->getName(),
            'is_super_admin_action' => (bool) ($user?->is_super_admin ?? false),
        ]);
    }
}
