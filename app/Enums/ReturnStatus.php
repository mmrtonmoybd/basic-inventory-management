<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case PendingInspection = 'pending_inspection';
    case PartiallyInspected = 'partially_inspected';
    case Inspected = 'inspected';

    public function label(): string
    {
        return match ($this) {
            self::PendingInspection => 'পরীক্ষার অপেক্ষায়',
            self::PartiallyInspected => 'আংশিক পরীক্ষা হয়েছে',
            self::Inspected => 'পরীক্ষা সম্পন্ন',
        };
    }
}
