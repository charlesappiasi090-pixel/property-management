<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `documents` – uploaded files attached to a property, unit, lease, tenant,
 * payment, expense or maintenance request.
 *
 * The `documentable` morph map is set up in `AppServiceProvider::boot()` so that
 * any model can have many documents.  The `tag` column lets the UI group
 * documents (e.g. "lease", "inspection", "id-proof").
 *
 * Safety: `BelongsToBusiness` supplies the `business_id` global scope, policies
 * enforce 404 for cross‑tenant access, and the controller always nests documents
 * under the parent record that owns them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            $table->morphs('documentable'); // documentable_id + documentable_type; also creates the index

            $table->string('tag')->nullable();      // e.g. "lease", "inspection", "id-proof"

            $table->string('storage_path');          // relative path inside storage/app/public/docs

            $table->string('original_name');

            $table->string('mime_type');

            $table->integer('size');                 // bytes

            $table->timestamps();

            $table->softDeletes();

            $table->index('tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};