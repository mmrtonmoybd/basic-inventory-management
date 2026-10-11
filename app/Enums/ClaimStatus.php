<?php

namespace App\Enums;

enum ClaimStatus: string
{
    case PendingReview = 'pending_review';
    case Reviewed = 'reviewed';
    case SupplierAgreed = 'supplier_agreed';
    case Approved = 'approved';
    case Dispatched = 'dispatched';
    case PartiallyReceived = 'partially_received';
    case Closed = 'closed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'যাচাইয়ের অপেক্ষায়',
            self::Reviewed => 'যাচাই হয়েছে',
            self::SupplierAgreed => 'সরবরাহকারী রাজি',
            self::Approved => 'অনুমোদিত',
            self::Dispatched => 'পাঠানো হয়েছে',
            self::PartiallyReceived => 'আংশিক পাওয়া গেছে',
            self::Closed => 'সম্পন্ন',
            self::Rejected => 'বাতিল',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Closed, self::Rejected], true);
    }

    public function canBeRejected(): bool
    {
        return in_array($this, [self::PendingReview, self::Reviewed, self::SupplierAgreed], true);
    }

    /**
     * @return list<self>
     */
    public static function awaitingApproval(): array
    {
        return [self::PendingReview, self::Reviewed, self::SupplierAgreed];
    }
}
