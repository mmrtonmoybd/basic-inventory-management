<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A standalone variant without attribute values — use ProductCatalogService for real combinations.
 *
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => Str::upper(fake()->unique()->bothify('SKU-#####??')),
            'combination_key' => (string) fake()->unique()->numberBetween(1, 9_999_999),
            'name' => 'সাধারণ',
            'low_stock_threshold' => 0,
            'status' => RecordStatus::Active,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => RecordStatus::Archived, 'archived_at' => now()]);
    }
}
