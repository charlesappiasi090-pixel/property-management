<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Models\Lease;
use App\Models\Tenant;
use App\Policies\RenewalNoticePolicy;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A renewal notice sent to a tenant for their lease.
 *
 * RenewalNotice safety: `BelongsToBusiness` supplies the `business` relation,
 * the automatic `business_id` scope, `createForBusiness()` and
 * `belongsToAnotherBusiness()`.  The policy (`RenewalNoticePolicy`) enforces 404
 * for cross‑tenant records and the standard `canInBusiness` check.
 *
 * @property int $id
 * @property int $business_id
 * @property int $lease_id
 * @property int $tenant_id
 * @property string $subject
 * @property string $body
 * @property \Carbon\Carbon|null $sent_at
 * @property \Carbon\Carbon|null $read_at
 * @property-read Lease $lease
 * @property-read Tenant $tenant
 * @property-read Business $business
 */
class RenewalNotice extends Model
{
    /** @use HasFactory<RenewalNoticeFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'renewal_notices';

    /** @var list<string> */
    protected $fillable = [
        'lease_id',
        'tenant_id',
        'subject',
        'body',
        'sent_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    /** -------------------------------------------------------------- */
    /* Relations                                                     */
    /* -------------------------------------------------------------- */

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /** -------------------------------------------------------------- */
    /* Scopes                                                        */
    /* -------------------------------------------------------------- */

    /**
     * Unread notices for the active business.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Notices sent within the last $days days.
     */
    public function scopeSentRecently(Builder $query, int $days = 30): Builder
    {
        return $query->where('sent_at', '>=', now()->subDays($days));
    }

    /** -------------------------------------------------------------- */
    /* Presentation                                                  */
    /* -------------------------------------------------------------- */

    /**
     * Human‑readable time since sent, or "read X time ago".
     */
    public function formattedSentAt(): string
    {
        return $this->sent_at?->diffForHumans() ?? 'unsent';
    }

    /**
     * Is this notice unread?
     */
    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /** -------------------------------------------------------------- */
    /* Policy / quota helpers (called from controllers)             */
    /* -------------------------------------------------------------- */

    /**
     * Enforce quota before creating a renewal notice.
     */
    public static function assertCanCreate(\App\Support\Tenancy\BusinessContext $context): void
    {
        $quota = new \App\Services\Billing\PlanQuota($context);

        $quota->assertCanAdd(
            app('business'),
            'renewal_notices'
        );
    }
}