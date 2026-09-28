<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only audit record.
 *
 * TWO IMPORTANT DIFFERENCES FROM A NORMAL MODEL
 *
 * 1. No SoftDeletes. Compliance requires that a log entry cannot be quietly
 *    removed through the application. Retention is a deliberate, separate
 *    decision.
 *
 * 2. No `updated_at`, and writes only. The migration creates `created_at`
 *    alone. There is no update path, no delete path, and no factory — a
 *    fabricated audit log is worse than a missing one.
 *
 * Rows are written by `App\Services\Audit\AuditLogger`, never directly from
 * a controller, so that the business_id, request_id, IP and before/after
 * snapshots are always populated consistently.
 *
 * @property string $event
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 */
class AuditLog extends Model
{
    /**
     * This model has no `updated_at` column.
     *
     * @var false
     */
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'user_id',
        'subject_type',
        'subject_id',
        'event',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'request_id',
        'route_name',
        'is_super_admin_action',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'is_super_admin_action' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The model that was changed. Polymorphic on purpose — see the migration.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Human summary for the activity feed.
     *
     * `subject_type` is nullable (a login or a payment webhook has no model
     * behind it), so the basename lookup is guarded: passing null into
     * `class_basename()` raises a deprecation on modern PHP and yields an
     * empty string, which would leave a dangling separator in every login
     * row of the feed.
     */
    public function summary(): string
    {
        $subject = $this->subject_type ? class_basename($this->subject_type) : '';

        return trim(($this->description ?: str_replace('_', ' ', $this->event)).' '.$subject);
    }

    /**
     * Scoped to the active business. Audit screens must never read across
     * tenants, so this bypasses the generic cross-tenant helper and applies
     * the filter explicitly.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }
}
