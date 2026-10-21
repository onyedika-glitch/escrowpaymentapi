<?php

namespace App\Support;

final class Money
{
    /** Convert a validated decimal amount (max 2 dp) to integer cents without float drift. */
    public static function toCents(string|int|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function format(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
