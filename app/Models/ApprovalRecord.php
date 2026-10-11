<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $approvable_type
 * @property int $approvable_id
 * @property string $action
 * @property string|null $from_status
 * @property string|null $to_status
 * @property string|null $comment
 * @property int|null $user_id
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['approvable_type', 'approvable_id', 'action', 'from_status', 'to_status', 'comment', 'user_id'])]
class ApprovalRecord extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    /**
     * @return MorphTo<Model, $this>
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
