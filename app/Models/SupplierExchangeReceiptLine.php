<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $supplier_exchange_receipt_id
 * @property int $supplier_exchange_line_id
 * @property int $quantity
 */
#[Fillable(['supplier_exchange_receipt_id', 'supplier_exchange_line_id', 'quantity'])]
class SupplierExchangeReceiptLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SupplierExchangeLine, $this>
     */
    public function exchangeLine(): BelongsTo
    {
        return $this->belongsTo(SupplierExchangeLine::class, 'supplier_exchange_line_id');
    }
}
