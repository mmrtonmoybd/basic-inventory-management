<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $return_inspection_id
 * @property int $return_line_id
 * @property int $good_quantity
 * @property int $damaged_quantity
 * @property string|null $damage_reason
 */
#[Fillable(['return_inspection_id', 'return_line_id', 'good_quantity', 'damaged_quantity', 'damage_reason'])]
class ReturnInspectionLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'good_quantity' => 'integer',
            'damaged_quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ReturnLine, $this>
     */
    public function returnLine(): BelongsTo
    {
        return $this->belongsTo(ReturnLine::class);
    }
}
