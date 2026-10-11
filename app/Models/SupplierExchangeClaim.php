<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property string|null $claim_no
 * @property int $damage_case_id
 * @property int $supplier_id
 * @property int $warehouse_id
 * @property ClaimStatus $status
 * @property string|null $supplier_message
 * @property string|null $supplier_reference
 * @property string|null $supplier_agreement_note
 * @property string|null $rejection_reason
 * @property int|null $dispatch_movement_id
 * @property int|null $created_by
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property int|null $supplier_agreed_by
 * @property CarbonImmutable|null $supplier_agreed_at
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property int|null $dispatched_by
 * @property CarbonImmutable|null $dispatched_at
 * @property int|null $closed_by
 * @property CarbonImmutable|null $closed_at
 * @property int|null $rejected_by
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'claim_no', 'damage_case_id', 'supplier_id', 'warehouse_id', 'status', 'supplier_message', 'supplier_reference',
    'supplier_agreement_note', 'rejection_reason', 'dispatch_movement_id', 'created_by',
    'reviewed_by', 'reviewed_at', 'supplier_agreed_by', 'supplier_agreed_at', 'approved_by', 'approved_at',
    'dispatched_by', 'dispatched_at', 'closed_by', 'closed_at', 'rejected_by', 'rejected_at',
])]
class SupplierExchangeClaim extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ClaimStatus::class,
            'reviewed_at' => 'datetime',
            'supplier_agreed_at' => 'datetime',
            'approved_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'closed_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SupplierExchangeLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplierExchangeLine::class);
    }

    /**
     * @return HasMany<SupplierExchangeReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(SupplierExchangeReceipt::class);
    }

    /**
     * @return MorphMany<ApprovalRecord, $this>
     */
    public function approvalRecords(): MorphMany
    {
        return $this->morphMany(ApprovalRecord::class, 'approvable')->orderBy('id');
    }

    /**
     * @return BelongsTo<DamageCase, $this>
     */
    public function damageCase(): BelongsTo
    {
        return $this->belongsTo(DamageCase::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
