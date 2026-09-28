<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Expense;
use App\Models\MaintenanceRequest;
use App\Policies\MessagePolicy;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A staff‑to‑tenant message within a business.
 *
 * Message safety: `BelongsToBusiness` supplies the `business` relation, the
 * automatic `business_id` scope, `createForBusiness()` and
 * `belongsToAnotherBusiness()`.  The policy (`MessagePolicy`) enforces 404 for
 * cross‑tenant records and the standard `canInBusiness` check.
 *
 * The `body` is stored as TEXT; the UI may render it with line breaks preserved.
 *
 * @property int $id
 * @property int $business_id
 * @property int $sender_id
 * @property int $tenant_id
 * @property string $subject
 * @property string $body
 * @property \Carbon\Carbon|null $read_at
 * @property-read \App\Models\Business $business
 * @property-read \App\Models\User $sender
 * @property-read \App\Models\Tenant $tenant
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'messages';

    /** @var list<string> */
    protected $fillable = [
        'sender_id',
        'tenant_id',
        'subject',
        'body',
        'read_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'read_at' => 'datetime',
    ];

    /** -------------------------------------------------------------- */
    /* Relations                                                     */
    /* -------------------------------------------------------------- */

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /** -------------------------------------------------------------- */
    /* Attachments (notes)                                           */
    /* -------------------------------------------------------------- */

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'noteable');
    }

    /** -------------------------------------------------------------- */
    /* Scopes                                                        */
    /* -------------------------------------------------------------- */

    /**
     * Unread messages for the active business.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Read messages for the active business.
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    /** -------------------------------------------------------------- */
    /* Presentation                                                  */
    /* -------------------------------------------------------------- */

    /**
     * Human‑readable time since creation, or "read X time ago".
     */
    public function formattedCreatedAt(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Is this message unread?
     */
    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /** -------------------------------------------------------------- */
    /* Quota / policy helpers (called from controllers)             */
    /* -------------------------------------------------------------- */

    /**
     * Enforce quota before creating a message.
     */
    public static function assertCanCreate(\App\Support\Tenancy\BusinessContext $context): void
    {
        $quota = new \App\Services\Billing\PlanQuota($context);

        $quota->assertCanAdd(
            app('business'),
            'messages'
        );
    }
}