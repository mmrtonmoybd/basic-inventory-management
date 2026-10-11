<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $supplier_exchange_claim_id
 * @property int $damage_case_line_id
 * @property int $product_variant_id
 * @property int|null $replacement_variant_id
 * @property int $claimed_quantity
 * @property int $approved_quantity
 * @property int $dispatched_quantity
 * @property int $received_quantity
 * @property int $written_off_quantity
 */
#[Fillable(['supplier_exchange_claim_id', 'damage_case_line_id', 'product_variant_id', 'replacement_variant_id', 'claimed_quantity', 'approved_quantity', 'dispatched_quantity', 'received_quantity', 'written_off_quantity'])]
class SupplierExchangeLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'claimed_quantity' => 'integer',
            'approved_quantity' => 'integer',
            'dispatched_quantity' => 'integer',
            'received_quantity' => 'integer',
            'written_off_quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SupplierExchangeClaim, $this>
     */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(SupplierExchangeClaim::class, 'supplier_exchange_claim_id');
    }

    /**
     * @return BelongsTo<DamageCaseLine, $this>
     */
    public function damageCaseLine(): BelongsTo
    {
        return $this->belongsTo(DamageCaseLine::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function replacementVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'replacement_variant_id');
    }

    /**
     * Units currently held by the supplier for this line.
     */
    public function outstandingQuantity(): int
    {
        return $this->dispatched_quantity - $this->received_quantity - $this->written_off_quantity;
    }
}
