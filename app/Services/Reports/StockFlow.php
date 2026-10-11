<?php

namespace App\Services\Reports;

use App\Enums\MovementType;
use App\Enums\StockCondition;

/**
 * Classifies ledger movement types into report columns for physical on-hand stock.
 */
final class StockFlow
{
    /**
     * @return array<string, list<string>>
     */
    public static function inboundColumns(): array
    {
        return [
            'opening_stock' => [MovementType::Opening->value],
            'stock_in' => [MovementType::StockIn->value],
            'returns' => [MovementType::ReturnReceipt->value],
            'replacements' => [MovementType::SupplierReceipt->value],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function outboundColumns(): array
    {
        return [
            'stock_out' => [MovementType::StockOut->value],
            'to_supplier' => [MovementType::SupplierDispatch->value],
        ];
    }

    /**
     * @return list<string>
     */
    public static function physicalConditions(): array
    {
        return array_map(fn (StockCondition $condition): string => $condition->value, StockCondition::physical());
    }
}
