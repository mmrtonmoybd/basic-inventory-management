<?php

namespace Database\Factories;

use App\Models\VariantAttribute;
use App\Models\VariantAttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VariantAttributeValue>
 */
class VariantAttributeValueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'variant_attribute_id' => VariantAttribute::factory(),
            'value' => fake()->unique()->word(),
            'sort_order' => 0,
        ];
    }
}
