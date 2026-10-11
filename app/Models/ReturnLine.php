<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_return_id
 * @property int $product_variant_id
 * @property int $quantity
 * @property int $good_quantity
 * @property int $damaged_quantity
 * @property string|null $reason
 * @property string|null $note
 */
#[Fillable(['customer_return_id', 'product_variant_id', 'quantity', 'good_quantity', 'damaged_quantity', 'reason', 'note'])]
class ReturnLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'good_quantity' => 'integer',
            'damaged_quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CustomerReturn, $this>
     */
    public function customerReturn(): BelongsTo
    {
        return $this->belongsTo(CustomerReturn::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function pendingQuantity(): int
    {
        return $this->quantity - $this->good_quantity - $this->damaged_quantity;
    }
}
