<?php

namespace App\Enums;

enum MovementType: string
{
    case Opening = 'opening';
    case StockIn = 'stock_in';
    case StockOut = 'stock_out';
    case ReturnReceipt = 'return_receipt';
    case ReturnInspection = 'return_inspection';
    case Damage = 'damage';
    case SupplierDispatch = 'supplier_dispatch';
    case SupplierReceipt = 'supplier_receipt';
    case SupplierWriteOff = 'supplier_write_off';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'শুরুর মজুদ',
            self::StockIn => 'স্টক ইন',
            self::StockOut => 'স্টক আউট',
            self::ReturnReceipt => 'কাস্টমার রিটার্ন',
            self::ReturnInspection => 'রিটার্ন পরীক্ষা',
            self::Damage => 'ক্ষতিগ্রস্ত মাল',
            self::SupplierDispatch => 'সরবরাহকারীর কাছে পাঠানো',
            self::SupplierReceipt => 'বদলি মাল গ্রহণ',
            self::SupplierWriteOff => 'সরবরাহকারীর কাছে বাদ',
            self::Adjustment => 'স্টক সমন্বয়',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::Opening => 'OPN',
            self::StockIn => 'SIN',
            self::StockOut => 'SOT',
            self::ReturnReceipt => 'RTN',
            self::ReturnInspection => 'INS',
            self::Damage => 'DMG',
            self::SupplierDispatch => 'DSP',
            self::SupplierReceipt => 'RCV',
            self::SupplierWriteOff => 'SWO',
            self::Adjustment => 'ADJ',
        };
    }

    /**
     * Movements that only shift units between physical conditions.
     */
    public function isInternalTransfer(): bool
    {
        return in_array($this, [self::ReturnInspection, self::Damage], true);
    }
}
