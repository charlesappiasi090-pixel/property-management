<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Enums\PermissionName;
use App\Policies\ExpensePolicy;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An expense incurred by a business.
 *
 * Expense safety: `BelongsToBusiness` supplies the `business` relation, the
 * automatic `business_id` scope, `createForBusiness()` and
 * `belongsToAnotherBusiness()`.  See the trait for why the scope matters in
 * addition to the policy.
 *
 * The `category` field is an enum – `ExpenseCategory` – so the UI can present
 * a sensible dropdown and the data layer enforces a fixed vocabulary.
 *
 * `status` is an enum (`pending`, `approved`, `reimbursed`, `written_off`)
 * because the UI needs to distinguish between "waiting for review", "okay,
 * we'll pay it", "the tenant paid us back", and "this is a loss".
 *
 * `amount` is DECIMAL(15,2) and is read via the `decimal:2` cast – the model
 * never exposes a PHP float.  All monetary arithmetic uses
 * `App\Support\Money\Money` which operates on integers (minor units) to avoid
 * the floating‑point rounding errors that plague property‑management systems.
 *
 * @property int $id
 * @property int $business_id
 * @property int $property_id
 * @property int $lease_id
 * @property int $unit_id
 * @property int $tenant_id
 * @property string $category
 * @property string $amount  (minor units as string, e.g. "125000" = 1250.00)
 * @property string $status  ('pending', 'approved', 'reimbursed', 'written_off')
 * @property string|null $description
 * @property string|null $receipt_path
 * @property \DateTimeInterface $expense_date
 * @property-read Property $property
 * @property-read Lease|null $lease
 * @property-read Tenant|null $tenant
 */
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'expenses';

    /** @var list<string> */
    protected $fillable = [
        'property_id',
        'lease_id',
        'unit_id',
        'tenant_id',
        'category',
        'amount',
        'status',
        'description',
        'receipt_path',
        'expense_date',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'status' => 'string',
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
     * Pending expenses only – those awaiting approval.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Approved expenses only.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Reimbursed expenses only.
     */
    public function scopeReimbursed(Builder $query): Builder
    {
        return $query->where('status', 'reimbursed');
    }

    /**
     * Written‑off expenses only.
     */
    public function scopeWrittenOff(Builder $query): Builder
    {
        return $query->where('status', 'written_off');
    }

    /** -------------------------------------------------------------- */
    /* Presentation                                                 */
    /* -------------------------------------------------------------- */

    /**
     * The amount formatted with the *business*'s currency symbol.
     */
    public function formattedAmount(): string
    {
        return \App\Support\Money\Money::of((int) $this->amount)
            ->format(\App\Models\Business::find($this->business_id)->currency,
                \App\Models\Business::find($this->business_id)->currencySymbol());
    }

    /**
     * Human‑readable status.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'approved' => 'Approved',
            'reimbursed' => 'Reimbursed',
            'written_off' => 'Written off',
            default => $this->status,
        };
    }

    /** -------------------------------------------------------------- */
    /* Category helper                                               */
    /* -------------------------------------------------------------- */

    /**
     * Friendly label for the UI.
     */
    public function categoryLabel(): string
    {
        return match ($this->category) {
            'maintenance' => 'Maintenance',
            'repairs' => 'Repairs',
            'utilities' => 'Utilities',
            'office_supplies' => 'Office supplies',
            'insurance' => 'Insurance',
            'other' => 'Other',
            default => $this->category,
        };
    }

    /** -------------------------------------------------------------- */
    /* Quota / policy helpers (called from controllers)             */
    /* -------------------------------------------------------------- */

    /**
     * Enforce quota before creating an expense, mirroring the pattern used
     * for payments/leases/property.  Throws `QuotaExceededException` if the
     * plan is full – the caller (controller) catches and flashes the message.
     *
     * Note: expenses may not have a plan limit in Phase 5, but this method
     * is here for future‑proofing and for when the business decides to
     * cap expenses per period.
     */
    public static function assertCanCreate(\App\Support\Tenancy\BusinessContext $context): void
    {
        $quota = new \App\Services\Billing\PlanQuota($context);

        $quota->assertCanAdd(
            app('business'),
            'expenses'
        );
    }
}