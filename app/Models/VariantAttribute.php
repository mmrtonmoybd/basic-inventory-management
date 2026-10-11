<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\VariantAttributeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_color
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'slug', 'is_color', 'sort_order'])]
class VariantAttribute extends Model
{
    /** @use HasFactory<VariantAttributeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_color' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<VariantAttributeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(VariantAttributeValue::class)->orderBy('sort_order')->orderBy('id');
    }
}
