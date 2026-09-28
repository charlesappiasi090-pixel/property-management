<?php

namespace App\Models;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Enums\PermissionName;
use App\Policies\MaintenancePolicy;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A maintenance request recorded for a property, unit, lease, or tenant.
 *
 * Maintenance request safety: `BelongsToBusiness` supplies the `business`
 * relation, the automatic `business_id` scope, `createForBusiness()` and
 * `belongsToAnotherBusiness()`.  See the trait for why the scope matters in
 * addition to the policy.
 *
 * The `priority` field is an enum (`MaintenancePriority`) so the UI can
 * distinguish between routine, urgent, and emergency – three states that a
 * plain boolean cannot express.
 *
 * The `status` field is an enum (`open`, `in_progress`, `completed`, `cancelled`)
 * so the UI can display the current state of the request.
 *
 * The `category` field uses the `MaintenanceCategory` enum so the UI can
 * present a sensible dropdown and the data layer enforces a fixed vocabulary.
 *
 * @property int $id
 * @property int $business_id
 * @property int $property_id
 * @property int $lease_id
 * @property int $unit_id
 * @property int $tenant_id
 * @property string $category
 * @property string $priority
 * @property string $status
 * @property string $reporter_name
 * @property string|null $reporter_email
 * @property text $description
 * @property \DateTimeInterface $requested_at
 * @property \DateTimeInterface|null $resolved_at
 * @property-read Property $property
 * @property-read Lease|null $lease
 * @property-read Unit|null $unit
 * @property-read Tenant|null $tenant
 */
class MaintenanceRequest extends Model
{
    /** @use HasFactory<MaintenanceRequestFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'maintenance_requests';

    /** @var list<string> */
    protected $fillable = [
        'property_id',
        'lease_id',
        'unit_id',
        'tenant_id',
        'category',
        'priority',
        'status',
        'reporter_name',
        'reporter_email',
        'description',
        'requested_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'requested_at' => 'date',
        'resolved_at' => 'date',
    ];

    /** -------------------------------------------------------------- */
    /* Relations                                                     */
    /* -------------------------------------------------------------- */

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /** -------------------------------------------------------------- */
    /* Query scopes                                                 */
    /* -------------------------------------------------------------- */

    /**
     * Open requests only – status = `open`.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /**
     * In‑progress requests only.
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Completed requests only.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Cancelled requests only.
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Routine priority only.
     */
    public function scopeRoutine(Builder $query): Builder
    {
        return $query->where('priority', 'routine');
    }

    /**
     * Urgent priority only.
     */
    public function scopeUrgent(Builder $query): Builder
    {
        return $query->where('priority', 'urgent');
    }

    /**
     * Emergency priority only.
     */
    public function scopeEmergency(Builder $query): Builder
    {
        return $query->where('priority', 'emergency');
    }

    /** -------------------------------------------------------------- */
    /* Presentation                                                 */
    /* -------------------------------------------------------------- */

    /**
     * The priority formatted as a human‑readable label.
     */
    public function priorityLabel(): string
    {
        return MaintenancePriority::from($this->priority)->label();
    }

    /**
     * The category formatted as a human‑readable label.
     */
    public function categoryLabel(): string
    {
        return MaintenanceCategory::from($this->category)->label();
    }

    /**
     * The status formatted as a human‑readable label.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => $this->status,
        };
    }

    /** -------------------------------------------------------------- */
    /* Quota / policy helpers (called from controllers)             */
    /* -------------------------------------------------------------- */

    /**
     * Enforce quota before creating a maintenance request, mirroring the
     * pattern used for properties/leases/expenses.  Throws
     * `QuotaExceededException` if the plan is full – the caller (controller)
     * catches and flashes the message.
     *
     * Note: maintenance requests may not have a plan limit in Phase 6, but
     * this method is here for future‑proofing and for when the business
     * decides to cap requests per period.
     */
    public static function assertCanCreate(\App\Support\Tenancy\BusinessContext $context): void
    {
        $quota = new \App\Services\Billing\PlanQuota($context);

        $quota->assertCanAdd(
            app('business'),
            'maintenance_requests'
        );
    }
}