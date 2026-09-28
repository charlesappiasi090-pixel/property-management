<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Models\Message;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An attachment note for a message.
 *
 * Note safety: `BelongsToBusiness` supplies the `business` relation, the
 * automatic `business_id` scope, `createForBusiness()` and
 * `belongsToAnotherBusiness()`.  The policy enforces 404 for cross‑tenant
 * records and the standard `canInBusiness` check.
 *
 * @property int $id
 * @property int $business_id
 * @property string $storage_path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property-read Message $message
 */
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'notes';

    /** @var list<string> */
    protected $fillable = [
        'storage_path',
        'original_name',
        'mime_type',
        'size',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'size' => 'integer',
    ];

    /** -------------------------------------------------------------- */
    /* Relations                                                     */
    /* -------------------------------------------------------------- */

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /** -------------------------------------------------------------- */
    /* Presentation                                                  */
    /* -------------------------------------------------------------- */

    /**
     * Human‑readable display size (e.g. "1.2 MB").
     */
    public function formattedSize(): string
    {
        $bytes = (int) $this->size;

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $kb = $bytes / 1024;

        if ($kb < 1024) {
            return number_format($kb, 1) . ' KB';
        }

        $mb = $kb / 1024;

        return number_format($mb, 2) . ' MB';
    }

    /** -------------------------------------------------------------- */
    /* Policy / quota helpers (called from controllers)             */
    /* -------------------------------------------------------------- */

    /**
     * Enforce quota before creating a note/attachment.
     */
    public static function assertCanCreate(\App\Support\Tenancy\BusinessContext $context): void
    {
        $quota = new \App\Services\Billing\PlanQuota($context);

        $quota->assertCanAdd(
            app('business'),
            'notes'
        );
    }
}