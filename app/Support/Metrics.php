<?php

namespace App\Support;

/** Metrik evaluasi klasifikasi tiga kelas. */
final class Metrics
{
    /**
     * @param list<string> $actual
     * @param list<string> $predicted
     * @return array{confusion:array,per_class:array,macro_f1:float,recall_high:float,accuracy:float,n:int}
     */
    public static function evaluate(array $actual, array $predicted): array
    {
        $levels = RiskModel::LEVELS;
        $confusion = [];
        foreach ($levels as $a) {
            foreach ($levels as $p) {
                $confusion[$a][$p] = 0;
            }
        }
        $correct = 0;
        foreach ($actual as $i => $a) {
            $confusion[$a][$predicted[$i]]++;
            $correct += $a === $predicted[$i] ? 1 : 0;
        }

        $perClass = [];
        $f1Sum = 0.0;
        foreach ($levels as $c) {
            $tp = $confusion[$c][$c];
            $fp = 0;
            $fn = 0;
            foreach ($levels as $o) {
                if ($o !== $c) {
                    $fp += $confusion[$o][$c];
                    $fn += $confusion[$c][$o];
                }
            }
            $precision = ($tp + $fp) > 0 ? $tp / ($tp + $fp) : 0.0;
            $recall = ($tp + $fn) > 0 ? $tp / ($tp + $fn) : 0.0;
            $f1 = ($precision + $recall) > 0 ? 2 * $precision * $recall / ($precision + $recall) : 0.0;
            $perClass[$c] = [
                'precision' => round($precision, 4),
                'recall' => round($recall, 4),
                'f1' => round($f1, 4),
                'support' => $tp + $fn,
            ];
            $f1Sum += $f1;
        }

        $n = count($actual);

        return [
            'confusion' => $confusion,
            'per_class' => $perClass,
            'macro_f1' => round($f1Sum / count($levels), 4),
            'recall_high' => $perClass['tinggi']['recall'],
            'accuracy' => $n > 0 ? round($correct / $n, 4) : 0.0,
            'n' => $n,
        ];
    }
}
