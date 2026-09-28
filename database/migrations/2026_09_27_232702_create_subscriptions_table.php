<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business' subscription to a plan.
 *
 * PROVIDER AGNOSTIC BY DESIGN (Phase 13 wires an actual gateway):
 *   - `provider` is NULL while no gateway is configured. The app then treats
 *     the subscription as manually managed, which is exactly what local and
 *     staging need.
 *   - The `provider_*` columns are nullable placeholders for the external
 *     identifiers a gateway returns (customer id, subscription id, invoice
 *     id). Adding them now means the later integration is a pure mapping
 *     exercise with no schema change.
 *   - All entitlement decisions read `status` + `current_period_end` from
 *     THIS table via a single service, never from the gateway at runtime, so
 *     a gateway outage can never lock a paying customer out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('plan_id')
                ->nullable()
                ->constrained('plans')
                // Restrict rather than cascade: deleting a plan that is in use
                // would silently strip a customer of their entitlements.
                ->restrictOnDelete();

            // trialing | active | past_due | canceled | expired | paused
            $table->string('status', 24)->default('trialing');

            $table->string('provider', 40)->nullable();
            $table->string('provider_customer_id', 191)->nullable();
            $table->string('provider_subscription_id', 191)->nullable();
            $table->string('provider_plan_id', 191)->nullable();

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();

            // Set when the user cancels: access continues until
            // current_period_end, then `status` flips to `expired` by the
            // scheduled task. Never cut access at the moment of cancellation.
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->unsignedInteger('quantity')->default(1);
            $table->json('meta')->nullable();

            $table->timestamps();

            // One row per business. A business has exactly one subscription at
            // a time; plan CHANGES mutate this row (or write a new one with a
            // closed `ended_at` and immediately supersede it) rather than
            // allowing parallel conflicting entitlements.
            $table->unique('business_id', 'subscriptions_business_unique');

            // Drives the "which businesses need attention" sweep run by the
            // billing reconciliation command.
            $table->index(['status', 'current_period_end'], 'subscriptions_status_period_end_index');
            $table->index('provider', 'subscriptions_provider_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
