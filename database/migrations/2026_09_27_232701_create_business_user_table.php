<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membership table linking `users` to `businesses`.
 *
 * WHY THIS EXISTS ALONGSIDE spatie/laravel-permission's `model_has_roles`:
 *
 *   - `business_user`      answers "does this person belong to this business?"
 *                          It drives the business switcher, tenant resolution
 *                          and the hard gate that stops a user from ever
 *                          loading another tenant's context.
 *   - `model_has_roles`    answers "what role do they hold in that business?"
 *
 * They are kept in sync by App\Services\Tenancy\BusinessMembershipService.
 * Keeping membership separate means we can revoke access to a business
 * (delete the pivot row) without touching a global role assignment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Free-text job title shown in the staff list, e.g. "Regional
            // Property Manager". Kept denormalised here so the members
            // screen needs no extra join.
            $table->string('job_title', 120)->nullable();

            // Flags *this* membership, not the user. A user can be an active
            // member of business A while being deactivated in business B.
            $table->boolean('is_active')->default(true);

            // Which business to land on when the user signs in. Exactly one
            // row per user is expected to hold this, but the constraint is
            // deliberately not a hard unique() — the fallback is "the oldest
            // membership" and the data layer tolerates zero defaults.
            $table->boolean('is_default')->default(false);

            $table->timestamp('joined_at')->nullable();
            $table->foreignId('invited_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // A user can only be a member of a business once.
            $table->unique(['business_id', 'user_id'], 'business_user_unique');

            // Supports the "list my businesses, newest activity first" query
            // behind the switcher without a full table scan.
            $table->index(['user_id', 'is_active'], 'business_user_user_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_user');
    }
};
