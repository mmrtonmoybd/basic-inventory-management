<?php

namespace App\Policies;

use App\Enums\ClaimStatus;
use App\Models\SupplierExchangeClaim;
use App\Models\User;

/**
 * Each workflow step requires its own permission and the claim to be in the matching state.
 */
class SupplierExchangeClaimPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('claims.view');
    }

    public function view(User $user, SupplierExchangeClaim $claim): bool
    {
        return $user->can('claims.view');
    }

    public function review(User $user, SupplierExchangeClaim $claim): bool
    {
        return $claim->status === ClaimStatus::PendingReview && $user->can('claims.review');
    }

    public function recordAgreement(User $user, SupplierExchangeClaim $claim): bool
    {
        return $claim->status === ClaimStatus::Reviewed && $user->can('claims.supplier_agreement');
    }

    /**
     * Final approval must come from someone other than the person who raised the claim.
     */
    public function approve(User $user, SupplierExchangeClaim $claim): bool
    {
        return $claim->status === ClaimStatus::SupplierAgreed
            && $claim->created_by !== $user->id
            && $user->can('claims.approve');
    }

    public function reject(User $user, SupplierExchangeClaim $claim): bool
    {
        return $claim->status->canBeRejected() && ($user->can('claims.approve') || $user->can('claims.review'));
    }

    public function dispatch(User $user, SupplierExchangeClaim $claim): bool
    {
        return $claim->status === ClaimStatus::Approved && $user->can('claims.dispatch');
    }

    public function receive(User $user, SupplierExchangeClaim $claim): bool
    {
        return in_array($claim->status, [ClaimStatus::Dispatched, ClaimStatus::PartiallyReceived], true) && $user->can('claims.receive');
    }

    public function closeShort(User $user, SupplierExchangeClaim $claim): bool
    {
        return in_array($claim->status, [ClaimStatus::Dispatched, ClaimStatus::PartiallyReceived], true) && $user->can('claims.approve');
    }
}
