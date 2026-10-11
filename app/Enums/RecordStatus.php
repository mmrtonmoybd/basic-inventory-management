<?php

namespace App\Enums;

enum RecordStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'চালু',
            self::Archived => 'আর্কাইভ',
        };
    }
}
