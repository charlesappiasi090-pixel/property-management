<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Enums\PermissionName;
use App\Policies\ReportPolicy;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A rent‑roll snapshot – a denormalised view of all active leases at a point in time.
 *
 * Rent‑roll safety: `BelongsToBusiness` supplies the `business` relation, the
 * automatic `business_id` scope, `createForBusiness()` and
 * `belongsToAnotherBusiness()`.  See the trait for why the scope matters in
 * addition to the policy.
 *
 * The `snapshot_at` date is the moment the roll was generated.  It is stored
 * so that historical comparisons (e.g. "compare Q3 2024 to Q3 2023") are
 * possible without re‑running the query.
 *
 * @property int $id
 * @property int $business_id
 * @property int $property_id
 * @property int $tenant_id
 * @property int $lease_id
 * @property string $tenant_name
 * @property string|null $unit_label
 * @property date $snapshot_at
 * @property date $lease_start_date
 * @property date|null $lease_end_date
 * @property decimal $monthly_rent
 * @property string $lease_status
 * @property-read Property $property
 * @property-read Tenant $tenant
 * @property-read Lease $lease
 */
class RentRoll extends Model
{
    /** @use HasFactory<RentRollFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'rent_rolls';

    /** @var list<string> */
    protected $fillable = [
        'property_id',
        'tenant_id',
        'lease_id',
        'tenant_name',
        'unit_label',
        'snapshot_at',
        'lease_start_date',
        'lease_end_date',
        'monthly_rent',
        'lease_status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'snapshot_at' => 'date',
        'lease_start_date' => 'date',
        'lease_end_date' => 'date',
        'monthly_rent' => 'decimal:2',
    ];

    /** -------------------------------------------------------------- */
    /* Relations                                                     */
    /* -------------------------------------------------------------- */

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    /** -------------------------------------------------------------- */
    /* Query scopes                                                 */
    /* -------------------------------------------------------------- */

    /**
     * Active (open) rent rolls only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('lease_status', 'active');
    }

    /**
     * Terminated rolls only.
     */
    public function scopeTerminated(Builder $query): Builder
    {
        return $query->where('lease_status', 'terminated');
    }

    /** -------------------------------------------------------------- */
    /* Presentation                                                 */
    /* -------------------------------------------------------------- */

    /**
     * The monthly rent formatted with the *business*'s currency symbol.
     */
    public function formattedMonthlyRent(): string
    {
        return \App\Support\Money\Money::of((int) $this->monthly_rent)
            ->format(\App\Models\Business::find($this->business_id)->currency,
                \App\Models\Business::find($this->business_id)->currencySymbol());
    }

    /** -------------------------------------------------------------- */
    /* Quota / policy helpers (called from controllers)             */
    /* -------------------------------------------------------------- */

    /**
     * Enforce quota before creating a rent‑roll snapshot, mirroring the
     * pattern used for properties/leases/expenses/maintenance.
     *
     * Note: rent rolls may not have a plan limit in Phase 7, but this method
     * is here for future‑proofing and for when the business decides to cap
     * the number of historical snapshots kept.
     */
    public static function assertCanCreate(\App\Support\Tenancy\BusinessContext $context): void
    {
        $quota = new \App\Services\Billing\PlanQuota($context);

        $quota->assertCanAdd(
            app('business'),
            'rent_rolls'
        );
    }
}