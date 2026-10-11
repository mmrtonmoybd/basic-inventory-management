<?php

namespace App\Services\Inventory;

use App\Enums\StockCondition;
use InvalidArgumentException;

/**
 * One quantity moving for a single variant: out of `$from`, into `$to`, or between them.
 */
final readonly class MovementLine
{
    public function __construct(
        public int $variantId,
        public int $quantity,
        public ?StockCondition $from = null,
        public ?StockCondition $to = null,
        public ?string $note = null,
        public ?string $errorKey = null,
    ) {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Movement quantities must be positive integers.');
        }

        if ($from === null && $to === null) {
            throw new InvalidArgumentException('A movement line needs a source or destination condition.');
        }

        if ($from === $to) {
            throw new InvalidArgumentException('Source and destination conditions must differ.');
        }
    }
}
