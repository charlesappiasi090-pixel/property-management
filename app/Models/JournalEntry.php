<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Expense;
use App\Models\MaintenanceRequest;
use App\Policies\JournalPolicy;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A general‑ledger journal entry for the business.
 *
 * Journal safety: `BelongsToBusiness` supplies the `business` relation, the
 * automatic `business_id` scope, `createForBusiness()` and
 * `belongsToAnotherBusiness()`.  The policy (`JournalPolicy`) enforces 404 for
 * cross‑tenant records and the standard `canInBusiness` check.
 *
 * The `transaction_type` is either `debit` or `credit`.  Amounts are stored as
 * decimal strings to match the `Money` class convention.
 *
 * @property int $id
 * @property int $business_id
 * @property string $description
 * @property decimal $amount
 * @property string $transaction_type
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property-read Business $business
 * @property-read Property|null $property (when reference_type = Property)
 * @property-read Expense|null $expense (when reference_type = Expense)
 * @property-read Lease|null $lease (when reference_type = Lease)
 * @property-read MaintenanceRequest|null $maintenanceRequest
 */
class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'journal_entries';

    /** @var list<string> */
    protected $fillable = [
        'description',
        'amount',
        'transaction_type',
        'reference_type',
        'reference_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_type' => 'string',
    ];

    /** -------------------------------------------------------------- */
    /* Relations                                                    */
    /* -------------------------------------------------------------- */

    public function property(): MorphMany
    {
        return $this->morphMany(Property::class, 'business');
    }

    public function expense(): MorphMany
    {
        return $this->morphMany(Expense::class, 'business');
    }

    public function lease(): MorphMany
    {
        return $this->morphMany(Lease::class, 'business');
    }

    public function maintenanceRequest(): MorphMany
    {
        return $this->morphMany(MaintenanceRequest::class, 'business');
    }

    /** -------------------------------------------------------------- */
    /* Scopes                                                       */
    /* -------------------------------------------------------------- */

    /**
     * Debit entries only.
     */
    public function scopeDebits(Builder $query): Builder
    {
        return $query->where('transaction_type', 'debit');
    }

    /**
     * Credit entries only.
     */
    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('transaction_type', 'credit');
    }

    /**
     * Entries for a given reference (property, expense, etc.).
     */
    public function scopeReferencing(Builder $query, ?string $type, ?int $id): Builder
    {
        return $type && $id
            ? $query->where('reference_type', $type)->where('reference_id', $id)
            : $query;
    }

    /** -------------------------------------------------------------- */
    /* Presentation                                                 */
    /* -------------------------------------------------------------- */

    /**
     * Human‑readable amount with sign for display.
     */
    public function formattedAmount(): string
    {
        $sign = $this->transaction_type === 'debit' ? '-' : '+';

        return $sign . number_format($this->amount, 2);
    }

    /** -------------------------------------------------------------- */
    /* Quota / policy helpers (called from controllers)             */
    /* -------------------------------------------------------------- */

    /**
     * Enforce quota before creating a journal entry.
     */
    public static function assertCanCreate(\App\Support\Tenancy\BusinessContext $context): void
    {
        $quota = new \App\Services\Billing\PlanQuota($context);

        $quota->assertCanAdd(
            app('business'),
            'journals'
        );
    }
}