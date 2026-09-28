<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `renewal_notices` – lease renewal notices sent to tenants.
 *
 * Each notice is attached to a lease and a tenant, with a sent_at timestamp
 * and a read flag.  The `BelongsToBusiness` scope ensures cross-tenant
 * isolation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renewal_notices', function (Blueprint $table) {
            $table->id();

            $table->morphs('business'); // business_id + business_type

            $table->foreignId('lease_id')
                ->constrained('leases')
                ->cascadeOnDelete();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->string('subject');

            $table->text('body');

            $table->timestamp('sent_at')->nullable();

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->softDeletes();

            $table->index(['lease_id', 'sent_at']);
            $table->index(['tenant_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_notices');
    }
};