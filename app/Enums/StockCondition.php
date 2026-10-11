<?php

namespace App\Enums;

enum StockCondition: string
{
    case Available = 'available';
    case Inspection = 'inspection';
    case Damaged = 'damaged';
    case WithSupplier = 'with_supplier';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'বিক্রয়যোগ্য',
            self::Inspection => 'পরীক্ষাধীন',
            self::Damaged => 'ক্ষতিগ্রস্ত',
            self::WithSupplier => 'সরবরাহকারীর কাছে',
        };
    }

    /**
     * Whether units in this condition are physically inside the warehouse.
     */
    public function isPhysical(): bool
    {
        return $this !== self::WithSupplier;
    }

    /**
     * @return list<self>
     */
    public static function physical(): array
    {
        return [self::Available, self::Inspection, self::Damaged];
    }
}
