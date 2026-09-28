<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable trail of every mutation performed inside the application.
 *
 * Design notes:
 *
 *  - `subject_type` + `subject_id` is a POLYMORPH reference, deliberately
 *    unconstrained by a foreign key. Adding one FK per possible model would
 *    force a migration every time the domain grows, and audit tables must
 *    survive the deletion of the thing they describe.
 *
 *  - `old_values` / `new_values` are JSON snapshots, not a diff. A diff
 *    cannot be replayed; a before/after pair can.
 *
 *  - `request_id` correlates every row written during one HTTP request or
 *    queued job, which is what makes a single user action reconstructable
 *    even when a controller touches several models.
 *
 *  - There are deliberately NO update/delete paths for this table in the
 *    application. Retention is a compliance decision, not an app feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Which tenant the action happened in. Nullable because
            // platform-level events (a new signup, a super-admin impersonation
            // of business X) are still attributable but may predate any
            // business context. Indexed first: every log screen filters by it.
            $table->foreignId('business_id')
                ->nullable()
                ->constrained('businesses')
                ->nullOnDelete();

            // The authenticated actor. Nullable for actions performed by
            // console commands, queue workers and scheduled jobs.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // What was changed. Polymorphic, intentionally not FK-constrained.
            //
            // NULLABLE on purpose: not every auditable action targets a model.
            // Sign-ins, sign-outs, failed permission checks, subscription
            // changes and document downloads all need a log entry, and
            // inventing a sentinel subject row to satisfy a NOT NULL
            // constraint would corrupt the trail.
            $table->string('subject_type', 191)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('event', 80);
            $table->string('description', 500)->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('method', 10)->nullable();

            // Groups every write made during a single request/job.
            $table->uuid('request_id')->nullable();

            $table->string('route_name', 191)->nullable();

            // Set when a super admin acts outside their own tenant, so
            // cross-tenant access is always distinguishable after the fact.
            $table->boolean('is_super_admin_action')->default(false);

            $table->timestamp('created_at')->nullable();

            // Covers the two dominant queries: "history for this record" and
            // "recent activity for this business".
            $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_index');
            $table->index(['business_id', 'created_at'], 'audit_logs_business_created_index');
            $table->index(['user_id', 'created_at'], 'audit_logs_user_created_index');
            $table->index('event', 'audit_logs_event_index');
            $table->index('request_id', 'audit_logs_request_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
