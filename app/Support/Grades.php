<?php

namespace App\Support;

/** Konversi nilai huruf atau angka 0-100 (maupun bobot 0-4 bila kolom bobot) menjadi bobot 0-4. */
final class Grades
{
    private const LETTERS = [
        'A' => 4.0, 'A-' => 3.7, 'AB' => 3.5, 'B+' => 3.3, 'B' => 3.0, 'B-' => 2.7,
        'BC' => 2.5, 'C+' => 2.3, 'C' => 2.0, 'C-' => 1.7, 'D+' => 1.3, 'D' => 1.0, 'E' => 0.0,
    ];

    /** Batas bawah nilai angka 0-100 untuk setiap bobot. */
    private const BANDS = [
        [85, 4.0], [80, 3.7], [75, 3.3], [70, 3.0], [65, 2.7],
        [60, 2.3], [55, 2.0], [50, 1.7], [45, 1.3], [40, 1.0], [0, 0.0],
    ];

    public static function parseNumber(string $raw): ?float
    {
        $v = trim(str_replace(['%', ' '], '', $raw));
        if (! preg_match('/^\d+([.,]\d+)?$/', $v)) {
            return null;
        }

        return (float) str_replace(',', '.', $v);
    }

    /**
     * Kembalikan bobot 0-4, atau null bila nilai tidak dikenal.
     *
     * @param string $scale 'score100' (huruf A-E atau angka 0-100 sesuai templat), 'bobot' (huruf atau bobot 0-4), atau 'huruf' (hanya huruf)
     */
    public static function toPoint(string $raw, string $scale = 'score100'): ?float
    {
        $v = strtoupper(trim(str_replace("\u{2212}", '-', $raw)));
        if ($v === '') {
            return null;
        }
        if (isset(self::LETTERS[$v])) {
            return self::LETTERS[$v];
        }
        if ($scale === 'huruf') {
            return null;
        }

        $n = self::parseNumber($v);
        if ($n === null || $n < 0) {
            return null;
        }

        if ($scale === 'bobot') {
            return $n <= 4.0 ? round($n, 2) : null;
        }

        if ($n > 100) {
            return null;
        }
        foreach (self::BANDS as [$min, $point]) {
            if ($n >= $min) {
                return $point;
            }
        }

        return null;
    }
}
