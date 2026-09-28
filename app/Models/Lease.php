<?php

namespace App\Models;

use App\Concerns\BelongsToBusiness;
use App\Enums\PermissionName;
use App\Enums\Role;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Policies\LeasePolicy;
use App\Services\Billing\PlanQuota;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A rental agreement between a tenant and a property (or unit).
 *
 * Lease safety: `BelongsToBusiness` supplies the `business` relation, the
 * automatic `business_id` scope, `createForBusiness()` and
 * `belongsToAnotherBusiness()`.  The policy adds a 404 for cross‑tenant
 * records and checks that the user is a member of the tenant's business.
 *
 * The monthly rent is stored as a decimal string (cast `decimal:2`) so that
 * the `App\Support\Money\Money` class can operate on it without floating‑point
 * rounding errors – the same invariant that exists for `Property.monthly_rent`
 * and `Unit.monthly_rent`.
 *
 * The `status` enum is deliberately tiny (`active` / `terminated`); a
 * terminated lease retains the record so the audit trail is complete.
 *
 * @property int $id
 * @property int $business_id
 * @property int $property_id
 * @property int $tenant_id
 * @property int|null $unit_id
 * @property string $start_date
 * @property string|null $end_date
 * @property string $monthly_rent
 * @property string|null $security_deposit
 * @property string $status
 * @property-read Tenant $tenant
 * @property-read Property $property
 * @property-read Unit|null $unit
 */
class Lease extends Model
{
    /** @use HasFactory<LeaseFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'leases';

    /** @var list<string> */
    protected $fillable = [
        'property_id',
        'unit_id',
        'tenant_id',
        'start_date',
        'end_date',
        'monthly_rent',
        'security_deposit',
        'status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'monthly_rent' => 'decimal:2',
        'security_deposit' => 'decimal:2',
        'status' => 'string',
    ];

    /** -------------------------------------------------------------- */
    /* Relations                                                     */
    /* -------------------------------------------------------------- */

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function unit(): ?BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /** -------------------------------------------------------------- */
    /* Query scopes                                                 */
    /* -------------------------------------------------------------- */

    /**
     * Active leases only – status = `active` and dates envelope today.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            });
    }

    /**
     * Terminated leases – status = `terminated`.
     */
    public function scopeTerminated(Builder $query): Builder
    {
        return $query->where('status', 'terminated');
    }

    /** -------------------------------------------------------------- */
    /* Validation helpers (used by requests)                       */
    /* -------------------------------------------------------------- */

    /**
     * Is this lease currently writable?  i.e. not terminated and the
     * monthly_rent can still be changed.
     */
    public function isWritable(): bool
    {
        return $this->status === 'active';
    }

    /** -------------------------------------------------------------- */
    /* Presentation                                                 */
    /* -------------------------------------------------------------- */

    /**
     * The rent formatted with the *business*'s currency symbol, not a
     * hard‑coded dollar sign – see `Business::currencySymbol()`.
     */
    public function formattedMonthlyRent(): string
    {
        return $this->monthly_rent === null
            ? 'Not set'
            : app('money')->of($this->monthly_rent)->format(
                app('business')->currency,
                app('business')->currencySymbol()
            );
    }

    /**
     * A human‑readable description, e.g. "01/03/2025 – 28/02/2026 (active)".
     */
    public function description(): string
    {
        $start = $this->start_date->format('d/m/Y');

        $end = $this->end_date
            ? $this->end_date->format('d/m/Y')
            : 'present';

        $status = $this->status === 'active' ? 'active' : 'terminated';

        return "{$start} – {$end} ({$status})";
    }

    /** -------------------------------------------------------------- */
    /* Policy / quota helpers (called from controllers)            */
    /* -------------------------------------------------------------- */

/**
 * Enforce quota before creating a lease, exactly as PropertyCatalog does
 * for properties/units.  Throws `QuotaExceededException` if the plan is
 * full – the caller (controller) catches and flashes the message.
 */
public static function assertCanCreate(BusinessContext $context): void
{
    $quota = new PlanQuota($context);

    $quota->assertCanAdd(
        app('business'),
        'leases'
    );
}

/**
 * Is this lease approaching its end date (within $days days)?
 */
public function isApproachingExpiry(int $days = 90): bool
{
    if ($this->status !== 'active' || is_null($this->end_date)) {
        return false;
    }

    $daysUntilExpiry = $this->end_date->diffInDays(now());

    return $daysUntilExpiry > 0 && $daysUntilExpiry <= $days;
}

/**
 * Days remaining on the lease (null if terminated or no end date).
 */
public function daysRemaining(): ?int
{
    if ($this->status !== 'active' || is_null($this->end_date)) {
        return null;
    }

    return $this->end_date->diffInDays(now());
}

/**
 * Upcoming renewal notices for the active business.
 */
public function scopeUpcomingNotices(Builder $query, int $days = 90): Builder
{
    return $query
        ->active()
        ->where('end_date', '<=', now()->addDays($days))
        ->whereNotNull('end_date');
}
}