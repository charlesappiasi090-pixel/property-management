<?php

namespace App\Models;

use App\Concerns\BelongsToBusiness;
use App\Enums\PropertyType;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A building or parcel a business manages.
 *
 * Tenant safety: `BelongsToBusiness` supplies the `business` relation, the
 * automatic `business_id` scope, `createForBusiness()` and
 * `belongsToAnotherBusiness()`. See the trait for why the scope matters in
 * addition to the policy.
 *
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property PropertyType $property_type
 * @property string|null $description
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country
 * @property string|null $owner_name
 * @property string|null $owner_email
 * @property string|null $owner_phone
 * @property int|null $year_built
 * @property bool $is_active
 */
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    /**
     * `model_type` is not a Laravel default and would have to be added at every
     * call site. Eloquent's automatic snake-case pluralisation gives
     * `property_factory` correctly without it.
     *
     * @var array<int, class-string>
     */
    protected $table = 'properties';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'property_type',
        'description',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'owner_name',
        'owner_email',
        'owner_phone',
        'year_built',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'property_type' => PropertyType::class,
            'year_built' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The lettable units on this property.
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /* ------------------------------------------------------------------ */
    /* Query scopes                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * The working portfolio: not archived, ordered for the list view.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Free-text search across the fields a landlord would type.
     *
     * Bound parameters only — the term is never interpolated into the SQL, so
     * a search for `100%` cannot alter the statement.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('name', 'like', $like)
                ->orWhere('address_line1', 'like', $like)
                ->orWhere('city', 'like', $like)
                ->orWhere('owner_name', 'like', $like);
        });
    }

    /* ------------------------------------------------------------------ */
    /* Presentation                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * The part of the address worth showing, in one line. Null when the
     * property has no address recorded yet — which is legal, because a
     * portfolio can be built up before the inspection paperwork arrives.
     */
    public function addressLine(): ?string
    {
        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postal_code,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Units that can be presented to a prospective tenant, i.e. everything not
     * archived.
     *
     * `commercial` and `land` have no residential inventory by definition
     * (`PropertyType::allowsResidentialUnits()`), so the UI hides the units
     * panel entirely for them rather than showing a table that can only ever
     * be empty.
     */
    public function showsUnitPanel(): bool
    {
        return $this->property_type->allowsResidentialUnits();
    }
}
