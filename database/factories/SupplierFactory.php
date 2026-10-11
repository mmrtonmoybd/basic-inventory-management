<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => Str::upper(fake()->unique()->lexify('SUP???')),
            'contact_person' => fake()->name(),
            'phone' => fake()->numerify('01#########'),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }
}
