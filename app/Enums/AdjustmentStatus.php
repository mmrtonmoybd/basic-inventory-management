<?php

namespace App\Enums;

enum AdjustmentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'অনুমোদনের অপেক্ষায়',
            self::Approved => 'অনুমোদিত',
            self::Rejected => 'বাতিল',
        };
    }
}
