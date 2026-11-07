<?php

namespace App\Support;

/**
 * Model risiko berbasis aturan berbobot (model cadangan pada dokumen rancangan).
 * Setiap faktor menyumbang poin; total poin dibandingkan dengan dua ambang.
 * Karena modelnya aditif, kontribusi tiap faktor sama persis dengan penjelasannya.
 */
final class RiskModel
{
    public const LEVELS = ['rendah', 'sedang', 'tinggi'];

    public const DEFAULT_THRESHOLDS = ['sedang' => 2.5, 'tinggi' => 5.0];

    public const MAX_SCORE = 10.0;

    /** @param array{attendance:float,ips:float,failed_ratio:float,ips_delta?:float|null} $f */
    public static function parts(array $f): array
    {
        $attendance = (float) $f['attendance'];
        $ips = (float) $f['ips'];
        $failed = (float) $f['failed_ratio'];
        $delta = (float) ($f['ips_delta'] ?? 0);

        return [
            'attendance' => round(min(3.0, max(0.0, (85 - $attendance) * 0.15)), 2),
            'ips' => round(min(3.0, max(0.0, (2.75 - $ips) * 2.0)), 2),
            'failed' => round(min(2.5, max(0.0, $failed * 8.0)), 2),
            'trend' => round(min(1.5, max(0.0, -$delta * 2.0)), 2),
        ];
    }

    public static function score(array $f): float
    {
        return round(array_sum(self::parts($f)), 2);
    }

    /** @param array{sedang:float,tinggi:float} $t */
    public static function classify(float $score, array $t): string
    {
        if ($score >= $t['tinggi']) {
            return 'tinggi';
        }

        return $score >= $t['sedang'] ? 'sedang' : 'rendah';
    }

    /**
     * Faktor penyebab model aturan, diurutkan dari kontribusi terbesar. Hanya faktor yang menambah poin.
     *
     * @return list<array{key:string,label:string,detail:string,points:float,share:float}>
     */
    public static function factors(array $f): array
    {
        return self::explain($f, self::parts($f));
    }

    /**
     * Susun faktor penyebab dari poin per faktor. Dipakai model aturan (poin aturan)
     * dan model ML (nilai SHAP pada skor risiko), dengan kalimat yang menyesuaikan arah nilai fitur.
     *
     * @param array{attendance?:float,ips?:float,failed?:float,trend?:float} $points poin tiap faktor
     * @return list<array{key:string,label:string,detail:string,points:float,share:float}>
     */
    public static function explain(array $f, array $points): array
    {
        $labels = [
            'attendance' => 'Kehadiran kuliah',
            'ips' => 'Indeks prestasi semester',
            'failed' => 'SKS bernilai rendah',
            'trend' => 'Arah perubahan IPS',
        ];

        $attendance = (float) ($f['attendance'] ?? 0);
        $ips = (float) ($f['ips'] ?? 0);
        $failedRatio = (float) ($f['failed_ratio'] ?? 0);
        $delta = round((float) ($f['ips_delta'] ?? 0), 2);

        $attendanceDetail = $attendance < 85.0
            ? 'Kehadiran rata-rata '.Fmt::pct($attendance).', di bawah batas aman 85%.'
            : 'Kehadiran rata-rata '.Fmt::pct($attendance).' (di atas ambang 85%, namun ikut berkontribusi pada pola model).';

        $ipsDetail = $ips < 2.75
            ? 'IPS semester ini '.Fmt::num($ips).', di bawah batas aman 2,75.'
            : 'IPS semester ini '.Fmt::num($ips).' (di atas ambang 2,75, namun ikut berkontribusi pada pola model).';

        $failedDetail = $failedRatio > 0.0
            ? Fmt::pct($failedRatio * 100).' SKS bernilai di bawah C (bobot 2,0).'
            : 'Tidak ada SKS bernilai di bawah C (0%), namun fitur ini ikut berkontribusi pada pola model.';

        if ($delta < 0.0) {
            $trendDetail = 'IPS turun '.Fmt::num(abs($delta)).' dari semester sebelumnya.';
        } elseif ($delta > 0.0) {
            $trendDetail = 'IPS naik '.Fmt::num($delta).' dari semester sebelumnya.';
        } else {
            $trendDetail = 'IPS tidak berubah (0,00) dibandingkan semester sebelumnya.';
        }

        $details = [
            'attendance' => $attendanceDetail,
            'ips' => $ipsDetail,
            'failed' => $failedDetail,
            'trend' => $trendDetail,
        ];

        $positive = array_filter(
            array_map(fn ($v) => round((float) $v, 2), array_intersect_key($points, $labels)),
            fn ($v) => $v > 0
        );
        $total = array_sum($positive);

        $out = [];
        foreach ($positive as $key => $value) {
            $out[] = [
                'key' => $key,
                'label' => $labels[$key],
                'detail' => $details[$key],
                'points' => $value,
                'share' => round($value / $total * 100, 1),
            ];
        }
        usort($out, fn ($a, $b) => $b['points'] <=> $a['points']);

        return $out;
    }

    /** @return list<string> */
    public static function suggestions(string $level, array $factors): array
    {
        $map = [
            'attendance' => 'Cek jadwal kuliah yang paling sering terlewat dan catat penyebabnya. Penyebab yang jelas memudahkan mencari jalan keluar.',
            'ips' => 'Pilih satu atau dua mata kuliah dengan nilai terendah dan jadwalkan waktu belajar tetap tiap minggu untuk keduanya.',
            'failed' => 'Tanyakan ke dosen pengampu bagian materi yang masih lemah, atau cari kelompok belajar untuk mata kuliah tersebut.',
            'trend' => 'Bandingkan beban semester ini dengan semester lalu. Kalau bebannya naik, diskusikan pengaturan waktunya dengan DPA.',
        ];

        $out = [];
        foreach (array_slice($factors, 0, 3) as $factor) {
            if (isset($map[$factor['key']])) {
                $out[] = $map[$factor['key']];
            }
        }
        if ($level !== 'rendah') {
            $out[] = 'Pertimbangkan membicarakan hasil ini dengan DPA. Kamu yang menentukan, dan izinnya bisa diatur di menu Privasi.';
        }
        if ($out === []) {
            $out[] = 'Pertahankan pola belajarmu. Impor data terbaru setiap ada nilai atau absensi baru.';
        }

        return $out;
    }

    /** Fitur satu semester dari daftar mata kuliah. Tiap item: credits, grade_point, attendance. */
    public static function featuresFrom(array $courses, ?array $previousCourses = null): array
    {
        $ips = self::ips($courses);
        $credits = array_sum(array_column($courses, 'credits'));
        $attendance = 0.0;
        $failedCredits = 0;
        foreach ($courses as $c) {
            $attendance += $c['attendance'] * $c['credits'];
            if ($c['grade_point'] < 2.0) {
                $failedCredits += $c['credits'];
            }
        }

        return [
            'attendance' => round($attendance / max(1, $credits), 2),
            'ips' => round($ips, 2),
            'failed_ratio' => round($failedCredits / max(1, $credits), 4),
            'ips_delta' => $previousCourses ? round($ips - self::ips($previousCourses), 2) : 0.0,
            'credits' => (int) $credits,
        ];
    }

    private static function ips(array $courses): float
    {
        $credits = array_sum(array_column($courses, 'credits'));
        $points = 0.0;
        foreach ($courses as $c) {
            $points += $c['grade_point'] * $c['credits'];
        }

        return $points / max(1, $credits);
    }
}
