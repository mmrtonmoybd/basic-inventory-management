<?php

namespace App\Http\Resources;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVariant
 */
class VariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $balances = $this->whenLoaded('balances');

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'status' => $this->status->value,
            'low_stock_threshold' => $this->low_stock_threshold,
            'value_ids' => $this->whenLoaded('attributeValues', fn () => $this->attributeValues->pluck('id')->all()),
            'product_name' => $this->whenLoaded('product', fn () => $this->product->name),
            'stock' => $this->when($this->relationLoaded('balances'), fn () => [
                'available' => (int) $balances->sum('available'),
                'inspection' => (int) $balances->sum('inspection'),
                'damaged' => (int) $balances->sum('damaged'),
                'with_supplier' => (int) $balances->sum('with_supplier'),
                'physical' => (int) $balances->sum(fn ($balance) => $balance->physicalOnHand()),
            ]),
        ];
    }
}
