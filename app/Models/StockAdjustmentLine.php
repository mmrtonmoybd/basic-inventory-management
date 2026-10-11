<?php

namespace App\Models;

use App\Enums\StockCondition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $stock_adjustment_id
 * @property int $product_variant_id
 * @property StockCondition $condition
 * @property int $system_quantity
 * @property int $counted_quantity
 * @property int $difference
 */
#[Fillable(['stock_adjustment_id', 'product_variant_id', 'condition', 'system_quantity', 'counted_quantity', 'difference'])]
class StockAdjustmentLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'condition' => StockCondition::class,
            'system_quantity' => 'integer',
            'counted_quantity' => 'integer',
            'difference' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
