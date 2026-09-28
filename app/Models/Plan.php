<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A purchasable subscription plan from the catalogue table.
 *
 * Plans contain NO behaviour — they are data. `features` is a JSON map of
 * capability => bool and the `max_*` columns are quotas (null = unlimited).
 * Nothing in the codebase branches on a plan name or code, so adding a plan
 * is an insert, not a deployment.
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'tagline',
        'description',
        'price',
        'currency',
        'billing_interval',
        'trial_days',
        'max_properties',
        'max_units',
        'max_staff',
        'max_documents',
        'features',
        'is_active',
        'is_public',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'trial_days' => 'integer',
            'max_properties' => 'integer',
            'max_units' => 'integer',
            'max_staff' => 'integer',
            'max_documents' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Is a named capability enabled on this plan?
     *
     * Unknown feature keys return FALSE, not true. A typo in a `can()` call
     * must never unlock something.
     */
    public function allows(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? false);
    }

    /**
     * How many of a resource this plan permits, or null when unlimited.
     */
    public function quotaFor(string $resource): ?int
    {
        $column = 'max_'.strtolower($resource);

        if (! in_array($column, ['max_properties', 'max_units', 'max_staff', 'max_documents'], strict: true)) {
            return null;
        }

        $value = $this->{$column};

        return $value === null ? null : (int) $value;
    }

    /**
     * Every feature key enabled on this plan.
     *
     * @return list<string>
     */
    public function enabledFeatures(): array
    {
        return array_keys(array_filter($this->features ?? []));
    }

    /**
     * Price formatted with the plan's own currency symbol.
     */
    public function formattedPrice(): string
    {
        $symbol = strtoupper($this->currency) === strtoupper(config('propertyhub.currency'))
            ? config('propertyhub.currency_symbol')
            : $this->currency;

        return $symbol.number_format((float) $this->price, 2);
    }

    public function intervalLabel(): string
    {
        return $this->billing_interval === 'yearly' ? 'year' : 'month';
    }

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
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_public', true);
    }

    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', $code)->first();
    }
}
