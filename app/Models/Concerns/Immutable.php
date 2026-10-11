<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Prevents ledger records from being changed or removed once written.
 */
trait Immutable
{
    public static function bootImmutable(): void
    {
        static::updating(function (): never {
            throw new LogicException(static::class.' records are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException(static::class.' records are immutable.');
        });
    }
}
