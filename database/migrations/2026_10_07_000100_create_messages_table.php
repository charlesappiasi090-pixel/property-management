<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `messages` – staff‑to‑tenant communications within a business.
 *
 * Each message is owned by a business (via `BelongsToBusiness` global scope)
 * and exchanged between a staff user and a tenant.  The `read_at` column lets
 * the portal inbox show unread vs read counts.
 *
 * Safety: `BelongsToBusiness` supplies the `business_id` scope, policies enforce
 * 404 for cross‑tenant records, and the controller always nests messages under
 * the business context.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->morphs('business'); // business_id + business_type

            $table->foreignId('sender_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->string('subject');

            $table->text('body');

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->softDeletes();

            $table->index(['business_id', 'read_at']);
            $table->index(['tenant_id', 'read_at']);
            $table->index(['sender_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};