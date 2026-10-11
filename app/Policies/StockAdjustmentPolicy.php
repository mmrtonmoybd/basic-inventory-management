<?php

namespace App\Policies;

use App\Enums\AdjustmentStatus;
use App\Models\StockAdjustment;
use App\Models\User;

class StockAdjustmentPolicy
{
    /**
     * Segregation of duties: nobody approves or rejects their own count adjustment.
     */
    public function decide(User $user, StockAdjustment $adjustment): bool
    {
        return $adjustment->status === AdjustmentStatus::Pending
            && $adjustment->requested_by !== $user->id
            && $user->can('adjustments.approve');
    }
}
