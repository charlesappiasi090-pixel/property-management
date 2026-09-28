<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Models\Payment;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A receipt issued for a payment.
 *
 * Receipt safety: `BelongsToBusiness` supplies the `business` relation, the
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
 * @property int $payment_id
 * @property string $amount  (minor units as string, e.g. "125000" = 1250.00)
 * @property string $receipt_number  unique identifier printed on the receipt
 * @property \DateTimeInterface $issued_at
 * @property string|null $issued_by  (name or id of the user who issued it)
 * @property-read Payment $payment
 * @property-read Business $business
 */
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'receipts';

    /** @var list<string> */
    protected $fillable = [
        'payment_id',
        'business_id',
        'amount',
        'receipt_number',
        'issued_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'amount' => 'decimal:2',
        'issued_at' => 'date',
    ];

    /** -------------------------------------------------------------- */
    /* Relations                                                     */
    /* -------------------------------------------------------------- */

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Business::class, 'business_id');
    }

    /** -------------------------------------------------------------- */
    /* Query scopes                                                 */
    /* -------------------------------------------------------------- */

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

    /** -------------------------------------------------------------- */
    /* Quota / policy helpers (called from controllers)             */
    /* -------------------------------------------------------------- */

    /**
     * Enforce quota before creating a receipt, mirroring the pattern used
     * for payments.  Receipts are strictly a presentation layer – the
     * quota check here is a back‑stop so that a receipt cannot be created
     * when the underlying payment is blocked.
     */
    public static function assertCanCreate(\App\Support\Tenancy\BusinessContext $context): void
    {
        $quota = new \App\Services\Billing\PlanQuota($context);

        $quota->assertCanAdd(
            app('business'),
            'receipts'
        );
    }
}