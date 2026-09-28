<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Database-backed notifications, used for the in-app notification centre that
 * Phase 4 (lease expiry warnings), Phase 5 (rent receipts / overdue notices)
 * and Phase 14 (tenant portal) all write into.
 *
 * The `business_id` column is an addition to Laravel's standard table. It is
 * NOT NULL-free: platform-level notices (e.g. "your trial ends in 3 days")
 * still belong to exactly one business, but genuinely global announcements
 * store NULL. The notification centre always scopes by the active business
 * so a user never sees another tenant's notices.
 *
 * The polymorphic `notifiable` pair means the SAME table serves both staff
 * (App\Models\User) and tenants (App\Models\Tenant, Phase 3) without a
 * separate inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('type');
            $table->morphs('notifiable');

            $table->foreignId('business_id')
                ->nullable()
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Drives the unread badge: count unread notices for this
            // notifiable pair, newest first, scoped to the active business.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_index');

            // Scopes the notification centre to the tenant.
            $table->index(['business_id', 'created_at'], 'notifications_business_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
