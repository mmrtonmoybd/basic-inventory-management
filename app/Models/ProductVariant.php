<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string|null $barcode
 * @property string $combination_key
 * @property string $name
 * @property int $low_stock_threshold
 * @property RecordStatus $status
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['product_id', 'sku', 'barcode', 'combination_key', 'name', 'low_stock_threshold', 'status', 'archived_at'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'low_stock_threshold' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsToMany<VariantAttributeValue, $this>
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(VariantAttributeValue::class, 'product_variant_attribute_values')
            ->withPivot('variant_attribute_id');
    }

    /**
     * @return HasMany<StockBalance, $this>
     */
    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    /**
     * @param  Builder<ProductVariant>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', RecordStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === RecordStatus::Active;
    }

    /**
     * Builds the canonical key used to guarantee a combination exists only once per product.
     *
     * @param  array<int, int>  $valueIds
     */
    public static function combinationKeyFor(array $valueIds): string
    {
        $valueIds = array_map('intval', $valueIds);
        sort($valueIds);

        return implode('-', $valueIds);
    }
}
