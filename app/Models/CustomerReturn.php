<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property string|null $return_no
 * @property int $warehouse_id
 * @property CarbonImmutable $return_date
 * @property string|null $customer_name
 * @property string|null $reference
 * @property string|null $reason
 * @property string|null $notes
 * @property ReturnStatus $status
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['return_no', 'warehouse_id', 'return_date', 'customer_name', 'reference', 'reason', 'notes', 'status', 'created_by'])]
class CustomerReturn extends Model
{
    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'status' => ReturnStatus::class,
        ];
    }

    /**
     * @return HasMany<ReturnLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ReturnLine::class);
    }

    /**
     * @return HasMany<ReturnInspection, $this>
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(ReturnInspection::class);
    }

    /**
     * @return MorphMany<StockMovement, $this>
     */
    public function movements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
