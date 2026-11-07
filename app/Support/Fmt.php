<?php

namespace App\Support;

/** Format angka gaya Indonesia: koma desimal, titik ribuan. */
final class Fmt
{
    public static function num(float|int|string|null $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }

    public static function pct(float|int|string|null $value, int $decimals = 0): string
    {
        return self::num($value, $decimals).'%';
    }
}
