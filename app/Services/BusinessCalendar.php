<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Resolves business dates in the configured business timezone (Asia/Dhaka by default).
 */
class BusinessCalendar
{
    public function timezone(): string
    {
        return config('inventory.business_timezone');
    }

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone())->startOfDay();
    }

    public function parse(?string $date): CarbonImmutable
    {
        return blank($date)
            ? $this->today()
            : CarbonImmutable::parse($date, $this->timezone())->startOfDay();
    }
}
