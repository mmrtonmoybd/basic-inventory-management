<?php

namespace App\Models;

use App\Enums\MovementType;
use App\Enums\StockCondition;
use App\Models\Concerns\Immutable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $stock_movement_id
 * @property int $stock_movement_line_id
 * @property int $product_variant_id
 * @property int $warehouse_id
 * @property MovementType $movement_type
 * @property StockCondition $condition
 * @property int $quantity_change
 * @property int $balance_after
 * @property CarbonImmutable $business_date
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['stock_movement_id', 'stock_movement_line_id', 'product_variant_id', 'warehouse_id', 'movement_type', 'condition', 'quantity_change', 'balance_after', 'business_date'])]
class StockLedgerEntry extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'movement_type' => MovementType::class,
            'condition' => StockCondition::class,
            'quantity_change' => 'integer',
            'balance_after' => 'integer',
            'business_date' => 'date',
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
