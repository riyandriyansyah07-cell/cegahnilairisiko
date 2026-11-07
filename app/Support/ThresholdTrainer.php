<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Melatih model aturan: mencari ambang "sedang" dan "tinggi" terbaik pada data latih,
 * lalu menguji pada data uji yang terpisah (30% terakhir pada pola indeks 7 dari 10).
 * Pembagian yang sama dikirim ke layanan model ML, sehingga kedua model diuji pada data uji yang sama.
 */
final class ThresholdTrainer
{
    /**
     * @param list<array> $rows
     * @return array{0:list<array>,1:list<array>} [data latih, data uji]
     */
    public static function split(array $rows): array
    {
        $train = [];
        $test = [];
        foreach (array_values($rows) as $i => $row) {
            if ($i % 10 < 7) {
                $train[] = $row;
            } else {
                $test[] = $row;
            }
        }
        if (count($train) < 30 || count($test) < 10) {
            throw new InvalidArgumentException('Data historis terlalu sedikit. Minimal 100 baris agar ada data latih dan data uji.');
        }

        return [$train, $test];
    }

    /**
     * @param list<array{attendance:float,ips:float,failed_ratio:float,ips_delta:float,label:string}> $rows
     * @return array{params:array{sedang:float,tinggi:float},metrics:array,train_rows:int,test_rows:int}
     */
    public static function fit(array $rows): array
    {
        return self::fitOn(...self::split($rows));
    }

    /** @return array{params:array{sedang:float,tinggi:float},metrics:array,train_rows:int,test_rows:int} */
    public static function fitOn(array $train, array $test): array
    {
        $train = self::scored($train);
        $test = self::scored($test);

        $best = null;
        for ($s = 1.0; $s <= 4.0; $s += 0.25) {
            for ($t = $s + 0.5; $t <= 8.0; $t += 0.25) {
                $m = self::evaluate($train, ['sedang' => $s, 'tinggi' => $t]);
                $objective = $m['macro_f1'] + 0.5 * $m['recall_high'];
                if ($best === null || $objective > $best['objective']) {
                    $best = ['objective' => $objective, 'sedang' => $s, 'tinggi' => $t];
                }
            }
        }

        $params = ['sedang' => $best['sedang'], 'tinggi' => $best['tinggi']];

        return [
            'params' => $params,
            'metrics' => self::evaluate($test, $params),
            'train_rows' => count($train),
            'test_rows' => count($test),
        ];
    }

    /** @param list<array> $rows baris dengan kunci score dan label */
    public static function evaluate(array $rows, array $thresholds): array
    {
        $actual = [];
        $predicted = [];
        foreach ($rows as $r) {
            $actual[] = $r['label'];
            $predicted[] = RiskModel::classify($r['score'], $thresholds);
        }

        return Metrics::evaluate($actual, $predicted);
    }

    private static function scored(array $rows): array
    {
        return array_map(fn (array $r) => $r + ['score' => RiskModel::score($r)], $rows);
    }
}
