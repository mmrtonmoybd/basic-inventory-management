<?php

namespace Database\Factories;

use App\Models\VariantAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VariantAttribute>
 */
class VariantAttributeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word());

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'is_color' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * @param  list<string>  $values
     */
    public function withValues(array $values): static
    {
        return $this->afterCreating(function (VariantAttribute $attribute) use ($values): void {
            foreach ($values as $position => $value) {
                $attribute->values()->create(['value' => $value, 'sort_order' => $position]);
            }
        });
    }
}
