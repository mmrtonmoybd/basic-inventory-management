<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\VariantAttributeValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $variant_attribute_id
 * @property string $value
 * @property string|null $code
 * @property string|null $swatch
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['variant_attribute_id', 'value', 'code', 'swatch', 'sort_order'])]
class VariantAttributeValue extends Model
{
    /** @use HasFactory<VariantAttributeValueFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<VariantAttribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(VariantAttribute::class, 'variant_attribute_id');
    }
}
