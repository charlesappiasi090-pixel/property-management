<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra columns on the default Laravel `users` table.
 *
 * The identity columns (name, email, password) are left exactly as Laravel
 * created them so that all first-party auth (Breeze, password broker,
 * email verification) keeps working untouched.
 *
 * Note the deliberate ABSENCE of a `business_id` column: a user may belong
 * to several businesses, so ownership lives on the `business_user` pivot.
 * `preferred_business_id` is only a UI convenience telling the app which
 * business to open first after login — it is not an authorisation source,
 * and `BusinessContext` re-validates it against `business_user` on every
 * request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('avatar_path', 255)->nullable()->after('phone');

            // Platform operator. Grants cross-tenant access for support and
            // migrations ONLY, is never assignable from the UI, and every
            // action taken while set is written to `audit_logs` with the
            // `super_admin` flag so it can be reviewed.
            $table->boolean('is_super_admin')->default(false)->after('avatar_path');

            // Suspended users keep their data and their audit trail but cannot
            // authenticate. Separate from `is_super_admin` so support can
            // disable an account without touching their privileges.
            $table->boolean('is_active')->default(true)->after('is_super_admin');

            $table->string('job_title', 120)->nullable()->after('is_active');

            $table->foreignId('preferred_business_id')
                ->nullable()
                ->after('job_title')
                ->constrained('businesses')
                ->nullOnDelete();

            // Feeds the "last seen" column in the staff list and lets the
            // audit log show where a session was established.
            $table->timestamp('last_login_at')->nullable()->after('preferred_business_id');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            // Per-user presentation overrides, used by date pickers and
            // money rendering when they differ from the business defaults.
            $table->string('locale', 12)->nullable()->after('last_login_ip');
            $table->string('timezone', 64)->nullable()->after('locale');

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['preferred_business_id']);
            $table->dropColumn([
                'phone',
                'avatar_path',
                'is_super_admin',
                'is_active',
                'job_title',
                'preferred_business_id',
                'last_login_at',
                'last_login_ip',
                'locale',
                'timezone',
                'deleted_at',
            ]);
        });
    }
};
