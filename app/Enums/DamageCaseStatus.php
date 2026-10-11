<?php

namespace App\Enums;

enum DamageCaseStatus: string
{
    case Open = 'open';
    case InClaim = 'in_claim';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'খোলা',
            self::InClaim => 'দাবি চলছে',
            self::Resolved => 'নিষ্পত্তি হয়েছে',
        };
    }
}
