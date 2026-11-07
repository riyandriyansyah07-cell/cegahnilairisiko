<?php

namespace App\Support;

/**
 * Validasi isi berkas impor KRS, nilai, dan absensi.
 * Semua baris harus valid. Satu kesalahan saja membatalkan seluruh impor.
 */
final class SheetValidator
{
    public const MAX_ROWS = 500;

    public const REQUIRED = ['semester', 'kode_mk', 'nama_mk', 'sks', 'nilai', 'kehadiran'];

    private const ALIASES = [
        'kode' => 'kode_mk', 'kodemk' => 'kode_mk', 'kode_mata_kuliah' => 'kode_mk',
        'mata_kuliah' => 'nama_mk', 'nama_mata_kuliah' => 'nama_mk', 'namamk' => 'nama_mk',
        'kehadiran_persen' => 'kehadiran', 'absensi' => 'kehadiran', 'absensi_persen' => 'kehadiran',
        'hadir' => 'kehadiran', 'persen_kehadiran' => 'kehadiran',
        'nilai_huruf' => 'nilai', 'nilai_angka' => 'nilai', 'nilai_bobot' => 'nilai', 'bobot' => 'nilai',
    ];

    /**
     * @param list<list<string|null>> $rows baris pertama berisi judul kolom
     * @return array{records:list<array>,errors:list<array{row:int,message:string}>,semesters:list<int>}
     */
    public static function validate(array $rows, string $ownerNim): array
    {
        $errors = [];
        if ($rows === []) {
            return self::fail('Berkas kosong.');
        }

        $rawHeaders = array_shift($rows);
        $gradeScale = self::detectGradeScale($rawHeaders);
        $header = array_map([self::class, 'normalizeHeader'], $rawHeaders);
        $missing = array_values(array_diff(self::REQUIRED, $header));
        if ($missing !== []) {
            return self::fail('Kolom wajib tidak ditemukan: '.implode(', ', $missing).'. Pakai templat yang disediakan.');
        }
        $col = array_flip($header);

        $dataRows = [];
        foreach ($rows as $i => $cells) {
            if (self::isBlank($cells)) {
                continue;
            }
            $dataRows[$i + 2] = $cells;
        }
        if ($dataRows === []) {
            return self::fail('Berkas tidak berisi baris data.');
        }
        if (count($dataRows) > self::MAX_ROWS) {
            return self::fail('Berkas berisi '.count($dataRows).' baris. Batasnya '.self::MAX_ROWS.' baris.');
        }

        $records = [];
        $seen = [];
        foreach ($dataRows as $line => $cells) {
            $get = fn (string $name) => isset($col[$name]) ? trim((string) ($cells[$col[$name]] ?? '')) : '';
            $rowErrors = [];

            $semester = $get('semester');
            if (! ctype_digit($semester) || (int) $semester < 1 || (int) $semester > 14) {
                $rowErrors[] = 'Semester harus bilangan 1 sampai 14.';
            }

            $code = self::neutralize($get('kode_mk'));
            if (! preg_match('/^[\'A-Za-z0-9 .\-_]{2,20}$/', $code)) {
                $rowErrors[] = 'Kode mata kuliah harus 2 sampai 20 karakter (huruf, angka, spasi, titik, strip).';
            }

            $name = self::neutralize($get('nama_mk'));
            if ($name === '' || mb_strlen($name) > 120) {
                $rowErrors[] = 'Nama mata kuliah wajib diisi, maksimal 120 karakter.';
            }

            $sks = $get('sks');
            if (! ctype_digit($sks) || (int) $sks < 1 || (int) $sks > 6) {
                $rowErrors[] = 'SKS harus bilangan 1 sampai 6.';
            }

            $point = Grades::toPoint($get('nilai'), $gradeScale);
            if ($point === null) {
                $rowErrors[] = 'Nilai "'.mb_substr($get('nilai'), 0, 12).'" tidak dikenal. Pakai huruf (A sampai E, termasuk D+) atau angka 0 sampai 100.';
            }

            $attendance = Grades::parseNumber($get('kehadiran'));
            if ($attendance === null || $attendance > 100) {
                $rowErrors[] = 'Kehadiran harus angka 0 sampai 100 (persen).';
            }

            if (isset($col['nim']) && $get('nim') !== '' && $get('nim') !== $ownerNim) {
                $rowErrors[] = 'NIM pada berkas tidak sama dengan NIM akunmu.';
            }

            $key = $semester.'|'.strtoupper($code);
            if ($rowErrors === [] && isset($seen[$key])) {
                $rowErrors[] = 'Mata kuliah ini sudah muncul di baris '.$seen[$key].' untuk semester yang sama.';
            }

            if ($rowErrors !== []) {
                foreach ($rowErrors as $message) {
                    $errors[] = ['row' => $line, 'message' => $message];
                }

                continue;
            }

            $seen[$key] = $line;
            $records[] = [
                'semester' => (int) $semester,
                'course_code' => strtoupper($code),
                'course_name' => $name,
                'credits' => (int) $sks,
                'grade_point' => $point,
                'grade_input' => mb_substr($get('nilai'), 0, 12),
                'attendance' => $attendance,
            ];
        }

        if ($errors !== []) {
            return ['records' => [], 'errors' => $errors, 'semesters' => []];
        }

        $semesters = array_values(array_unique(array_column($records, 'semester')));
        sort($semesters);

        return ['records' => $records, 'errors' => [], 'semesters' => $semesters];
    }

    public static function normalizeHeader(?string $h): string
    {
        $h = strtolower(trim((string) $h));
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
        $h = preg_replace('/[^a-z0-9]+/', '_', $h);
        $h = trim($h, '_');

        return self::ALIASES[$h] ?? $h;
    }

    private static function detectGradeScale(array $rawHeaders): string
    {
        foreach ($rawHeaders as $h) {
            $clean = strtolower(trim((string) $h));
            $clean = preg_replace('/^\xEF\xBB\xBF/', '', $clean);
            $clean = trim((string) preg_replace('/[^a-z0-9]+/', '_', $clean), '_');
            if (in_array($clean, ['nilai_bobot', 'bobot'], true)) {
                return 'bobot';
            }
            if ($clean === 'nilai_huruf') {
                return 'huruf';
            }
        }

        return 'score100';
    }

    /** Sel teks yang diawali karakter rumus dinetralkan dengan tanda petik. */
    public static function neutralize(string $text): string
    {
        $text = trim($text);

        return preg_match('/^[=+\-@\t\r]/', $text) ? "'".$text : $text;
    }

    private static function isBlank(array $cells): bool
    {
        foreach ($cells as $c) {
            if (trim((string) $c) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function fail(string $message): array
    {
        return ['records' => [], 'errors' => [['row' => 1, 'message' => $message]], 'semesters' => []];
    }
}
