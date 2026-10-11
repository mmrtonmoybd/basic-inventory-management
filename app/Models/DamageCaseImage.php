<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $damage_case_id
 * @property string $path
 * @property string $original_name
 * @property int $size
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['damage_case_id', 'path', 'original_name', 'size'])]
class DamageCaseImage extends Model
{
    /**
     * @return BelongsTo<DamageCase, $this>
     */
    public function damageCase(): BelongsTo
    {
        return $this->belongsTo(DamageCase::class);
    }
}
