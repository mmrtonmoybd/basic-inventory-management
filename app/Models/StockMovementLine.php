<?php

namespace App\Models;

use App\Enums\StockCondition;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $stock_movement_id
 * @property int $product_variant_id
 * @property StockCondition|null $from_condition
 * @property StockCondition|null $to_condition
 * @property int $quantity
 * @property string|null $note
 */
#[Fillable(['stock_movement_id', 'product_variant_id', 'from_condition', 'to_condition', 'quantity', 'note'])]
class StockMovementLine extends Model
{
    use Immutable;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'from_condition' => StockCondition::class,
            'to_condition' => StockCondition::class,
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<StockMovement, $this>
     */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
