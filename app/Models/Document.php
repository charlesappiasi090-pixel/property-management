<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\MaintenanceRequest;
use App\Policies\DocumentPolicy;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An uploaded file attached to any business‑scoped model.
 *
 * Document safety: `BelongsToBusiness` supplies the `business` relation, the
 * automatic `business_id` scope, `createForBusiness()` and
 * `belongsToAnotherBusiness()`.  The policy (`DocumentPolicy`) enforces 404 for
 * cross‑tenant records and the standard `canInBusiness` check.
 *
 * The `tag` field is a free‑form string; the UI may use it to categorise
 * documents (e.g. "lease", "inspection", "id-proof").
 *
 * @property int $id
 * @property int $business_id
 * @property string $tag
 * @property string $storage_path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property-read Property $property (when attached)
 * @property-read Tenant $tenant (when attached)
 * @property-read Lease $lease (when attached)
 * @property-read Payment $payment (when attached)
 * @property-read Expense $expense (when attached)
 * @property-read MaintenanceRequest $maintenanceRequest (when attached)
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'documents';

    /** @var list<string> */
    protected $fillable = [
        'tag',
        'storage_path',
        'original_name',
        'mime_type',
        'size',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'size' => 'integer',
    ];

    /** -------------------------------------------------------------- */
    /* Relations                                                     */
    /* -------------------------------------------------------------- */

    public function property(): MorphMany
    {
        return $this->morphMany(Property::class, 'documentable');
    }

    public function tenant(): MorphMany
    {
        return $this->morphMany(Tenant::class, 'documentable');
    }

    public function lease(): MorphMany
    {
        return $this->morphMany(Lease::class, 'documentable');
    }

    public function payment(): MorphMany
    {
        return $this->morphMany(Payment::class, 'documentable');
    }

    public function expense(): MorphMany
    {
        return $this->morphMany(Expense::class, 'documentable');
    }

    public function maintenanceRequest(): MorphMany
    {
        return $this->morphMany(MaintenanceRequest::class, 'documentable');
    }

    /** -------------------------------------------------------------- */
    /* Scopes                                                        */
    /* -------------------------------------------------------------- */

    /**
     * Documents for the active business only (via BelongsToBusiness scope).
     */
    public function scopeBusiness(Builder $query): Builder
    {
        return $query->where('business_id', app(BusinessContext::class)->id());
    }

    /**
     * Filter by tag, if provided.
     */
    public function scopeTag(Builder $query, ?string $tag): Builder
    {
        return $tag ? $query->where('tag', $tag) : $query;
    }

    /** -------------------------------------------------------------- */
    /* Presentation                                                  */
    /* -------------------------------------------------------------- */

    /**
     * The human‑readable display size (e.g. "1.2 MB").
     */
    public function formattedSize(): string
    {
        $bytes = (int) $this->size;

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $kb = $bytes / 1024;

        if ($kb < 1024) {
            return number_format($kb, 1) . ' KB';
        }

        $mb = $kb / 1024;

        return number_format($mb, 2) . ' MB';
    }

    /** -------------------------------------------------------------- */
    /* Quota / policy helpers (called from controllers)             */
    /* -------------------------------------------------------------- */

    /**
     * Enforce quota before creating a document, mirroring the pattern used
     * for properties/leases/expenses/maintenance.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function assertCanCreate(\App\Support\Tenancy\BusinessContext $context): void
    {
        $quota = new \App\Services\Billing\PlanQuota($context);

        $quota->assertCanAdd(
            app('business'),
            'documents'
        );
    }
}