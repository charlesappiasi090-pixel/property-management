<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Models\Lease;
use App\Services\Billing\PlanQuota;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single rent payment recorded against a lease.
 *
 * Payment safety: `BelongsToBusiness` supplies the `business` relation, the
 * automatic `business_id` scope, `createForBusiness()` and
 * `belongsToAnotherBusiness()`.  See the trait for why the scope matters in
 * addition to the policy.
 *
 * The `amount` column is DECIMAL(15,2) and is read via the `decimal:2` cast –
 * the model never exposes a PHP float.  All monetary arithmetic uses
 * `App\Support\Money\Money` which operates on integers (minor units) to
 * avoid the floating‑point rounding errors that plague property‑management
 * systems that deal with many hundreds of transactions.
 *
 * @property int $id
 * @property int $business_id
 * @property int $lease_id
 * @property string $amount  (minor units as string, e.g. "125000" = 1250.00)
 * @property string $status  ('active', 'failed', 'refunded')
 * @property string|null $method  ('cash', 'bank_transfer', 'credit_card', etc.)
 * @property string|null $reference  (check number, txn ID, etc.)
 * @property \DateTimeInterface $payment_date
 * @property-read Lease $lease
 * @property-read Business $business
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'payments';

    /** @var list<string> */
    protected $fillable = [
        'lease_id',
        'business_id',
        'amount',
        'status',
        'method',
        'reference',
        'payment_date',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'status' => 'string',
    ];

    /** -------------------------------------------------------------- */
    /* Relations                                                     */
    /* -------------------------------------------------------------- */

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Business::class, 'business_id');
    }

    /** -------------------------------------------------------------- */
    /* Query scopes                                                 */
    /* -------------------------------------------------------------- */

    /**
     * Active (successful) payments only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Failed payments.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Refunded payments.
     */
    public function scopeRefunded(Builder $query): Builder
    {
        return $query->where('status', 'refunded');
    }

    /** -------------------------------------------------------------- */
    /* Presentation                                                 */
    /* -------------------------------------------------------------- */

    /**
     * The amount formatted with the *business*'s currency symbol, not a
     * hard‑coded dollar sign – see `Business::currencySymbol()`.
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
            'active' => 'Active',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
            default => $this->status,
        };
    }

    /** -------------------------------------------------------------- */
    /* Quota / policy helpers (called from controllers)             */
    /* -------------------------------------------------------------- */

    /**
     * Enforce quota before creating a payment, exactly as
     * `PropertyCatalog::assertCanAdd` does for properties/units/leases.
     * Throws `QuotaExceededException` if the plan is full – the caller
     * (controller) catches and flashes the message.
     *
     * Note: leases may not have a plan limit in Phase 3, but this method
     * is here for future‑proofing and for when Phase 4 adds a "payments
     * per‑lease" limit.
     */
    public static function assertCanCreate(\App\Support\Tenancy\BusinessContext $context): void
    {
        $quota = new \App\Services\Billing\PlanQuota($context);

        $quota->assertCanAdd(
            app('business'),
            'payments'
        );
    }
}