<?php

namespace App\Models;

use App\Concerns\BelongsToBusiness;
use App\Support\Money\Money;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One lettable dwelling or space inside a property.
 *
 * `business_id` is a deliberate denormalisation of the property's tenant. See
 * the `units` migration for why, and for why the only writer is
 * `App\Services\Portfolio\PropertyCatalog` — which never accepts a
 * caller-supplied value.
 *
 * @property int $id
 * @property int $business_id
 * @property int $property_id
 * @property string $label
 * @property int|null $bedrooms
 * @property string|null $bathrooms
 * @property int|null $square_feet
 * @property string|null $floor
 * @property string|null $monthly_rent
 * @property string|null $notes
 * @property bool $is_active
 * @property-read Property $property
 */
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'property_id',
        'label',
        'bedrooms',
        'bathrooms',
        'square_feet',
        'floor',
        'monthly_rent',
        'notes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bedrooms' => 'integer',
            // DECIMAL(3,1) and DECIMAL(15,2) are read as STRINGS on purpose:
            // the `decimal` cast with no precision returns a float, and float
            // is the one thing App\Support\Money\Money exists to avoid.
            'bathrooms' => 'decimal:1',
            'square_feet' => 'integer',
            'monthly_rent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /* ------------------------------------------------------------------ */
    /* Query scopes                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
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
            $query->where('label', 'like', $like)->orWhere('floor', 'like', $like);
        });
    }

    /* ------------------------------------------------------------------ */
    /* Presentation                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * The asking rent as a `Money` value, or null when it has not been set.
     *
     * The caller formats it with the *business'* currency, not a hard-coded
     * symbol — see `Business::currencySymbol()`.
     */
    public function askingRent(): ?Money
    {
        return $this->monthly_rent === null ? null : Money::of($this->monthly_rent);
    }

    /**
     * "2 bed · 1.5 bath · 780 sq ft", omitting anything not recorded.
     *
     * A specification is a *list of known facts*, so a studio with no
     * bedrooms recorded should not print "0 bed" — it prints what it knows.
     */
    public function specification(): string
    {
        $parts = [];

        if ($this->bedrooms !== null) {
            $parts[] = $this->bedrooms === 0
                ? 'Studio'
                : trans_choice('{1} :count bed|[2,*] :count beds', $this->bedrooms, ['count' => $this->bedrooms]);
        }

        if ($this->bathrooms !== null) {
            $bathrooms = rtrim(rtrim((string) $this->bathrooms, '0'), '.');
            $parts[] = $bathrooms.' '.trans_choice('bath', (float) $bathrooms === 1.0 ? 1 : 2);
        }

        if ($this->square_feet !== null) {
            $parts[] = number_format($this->square_feet).' sq ft';
        }

        return $parts === [] ? 'No specification recorded' : implode(' · ', $parts);
    }
}
