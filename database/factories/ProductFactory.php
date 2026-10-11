<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::title(fake()->unique()->words(3, true)),
            'code' => Str::upper(fake()->unique()->bothify('P-####??')),
            'unit' => 'pcs',
            'status' => RecordStatus::Active,
        ];
    }
}
