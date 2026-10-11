<?php

namespace App\Models;

use App\Enums\DamageCaseStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $case_no
 * @property int $warehouse_id
 * @property int|null $supplier_id
 * @property int|null $customer_return_id
 * @property int|null $stock_movement_id
 * @property string $source
 * @property CarbonImmutable $damage_date
 * @property string $reason
 * @property string|null $internal_note
 * @property string|null $supplier_message
 * @property DamageCaseStatus $status
 * @property int|null $reported_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['case_no', 'warehouse_id', 'supplier_id', 'customer_return_id', 'stock_movement_id', 'source', 'damage_date', 'reason', 'internal_note', 'supplier_message', 'status', 'reported_by'])]
class DamageCase extends Model
{
    protected function casts(): array
    {
        return [
            'damage_date' => 'date',
            'status' => DamageCaseStatus::class,
        ];
    }

    /**
     * @return HasMany<DamageCaseLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(DamageCaseLine::class);
    }

    /**
     * @return HasMany<DamageCaseImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(DamageCaseImage::class);
    }

    /**
     * @return HasMany<SupplierExchangeClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(SupplierExchangeClaim::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<CustomerReturn, $this>
     */
    public function customerReturn(): BelongsTo
    {
        return $this->belongsTo(CustomerReturn::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
