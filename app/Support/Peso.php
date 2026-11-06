<?php

namespace App\Support;

/** Small peso formatting helpers used by the rental pages and models. */
class Peso
{
    /** 700 -> "₱700", 700.5 -> "₱700.50", 3500 -> "₱3,500" */
    public static function format(float|int|string|null $amount): string
    {
        $value = (float) $amount;

        return '₱' . number_format($value, fmod($value, 1.0) === 0.0 ? 0 : 2);
    }

    /** "₱700 – ₱3,500", or just "₱700" when both ends are the same. */
    public static function range(float|int|string|null $min, float|int|string|null $max): string
    {
        return round((float) $min, 2) === round((float) $max, 2)
            ? self::format($min)
            : self::format($min) . ' – ' . self::format($max);
    }
}