<?php

namespace App\Models;

use App\Concerns\BelongsToBusiness;
use App\Enums\Role;
use App\Enums\PermissionName;
use App\Models\Lease;
use App\Policies\LeasePolicy;
use App\Policies\TenantPolicy;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tenant / landlord external to the Spatie permission system.
 *
 * Tenant safety: `BelongsToBusiness` supplies the `business` relation, the
 * automatic `business_id` scope, `createForBusiness()` and
 * `belongsToAnotherBusiness()`.  See the trait for why the scope matters in
 * addition to the policy.
 *
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country
 * @property bool $is_active
 * @property \Illuminate\Support\Collection<\App\Models\Lease> $leases
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'tenants';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'contact_name',
        'email',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** @var list<string> */
    protected $appends = [];

    /** -------------------------------------------------------------- */
    /* Relations                                                     */
    /* -------------------------------------------------------------- */

    /**
     * The leases associated with this tenant.
     */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class, 'tenant_id');
    }

    /**
     * The active lease, if any – the one whose status is `active` and whose
     * dates envelop today.  Used by the UI to quickly show "current lease"
     * without a query in the view.
     */
    public function activeLease(): ?Lease
    {
        return $this->leases()
            ->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->first();
    }

    /** -------------------------------------------------------------- */
    /* Query scopes                                                 */
    /* -------------------------------------------------------------- */

    /**
     * Working tenants only – excludes soft‑deleted rows.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** -------------------------------------------------------------- */
    /* Presentation                                                 */
    /* -------------------------------------------------------------- */

    /**
     * The full name shown in lists and selects – name + contact name if
     * available.
     */
    public function displayName(): string
    {
        $parts = [];

        if ($this->contact_name) {
            $parts[] = $this->contact_name;
        }

        $parts[] = $this->name;

        return $parts === [] ? 'Unnamed tenant' : implode(' ', $parts);
    }

    /**
     * The email address to use for correspondence – the public `email`
     * field, falling back to the user‑associated email if blank.
     */
    public function correspondenceEmail(): string
    {
        return $this->email ?? 'not-recorded@example.com';
    }
}