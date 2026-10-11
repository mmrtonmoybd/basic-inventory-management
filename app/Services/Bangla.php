<?php

namespace App\Services;

/**
 * Formats numbers and dates for the Bengali interface.
 */
final class Bangla
{
    private const DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

    /**
     * Converts Western digits to Bengali digits, e.g. "1,250" → "১,২৫০".
     */
    public static function digits(string|int $value): string
    {
        return str_replace(range(0, 9), self::DIGITS, (string) $value);
    }

    /**
     * A whole number with thousands separators in Bengali digits.
     */
    public static function number(int|float|string|null $value): string
    {
        return self::digits(number_format((int) $value));
    }
}
