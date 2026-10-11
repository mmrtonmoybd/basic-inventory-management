<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $damage_case_id
 * @property int $product_variant_id
 * @property int $quantity
 * @property int $claimed_quantity
 */
#[Fillable(['damage_case_id', 'product_variant_id', 'quantity', 'claimed_quantity'])]
class DamageCaseLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'claimed_quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DamageCase, $this>
     */
    public function damageCase(): BelongsTo
    {
        return $this->belongsTo(DamageCase::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function unclaimedQuantity(): int
    {
        return $this->quantity - $this->claimed_quantity;
    }
}
