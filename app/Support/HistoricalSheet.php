<?php

namespace App\Support;

/** Validasi berkas data historis anonim untuk pelatihan model (UC-10). */
final class HistoricalSheet
{
    public const MAX_ROWS = 5000;

    private const REQUIRED = ['kehadiran', 'ips', 'rasio_sks_tidak_lulus', 'perubahan_ips', 'label'];

    private const ALIASES = [
        'attendance' => 'kehadiran', 'failed_ratio' => 'rasio_sks_tidak_lulus', 'rasio_gagal' => 'rasio_sks_tidak_lulus',
        'ips_delta' => 'perubahan_ips', 'delta_ips' => 'perubahan_ips',
    ];

    private const IDENTITY = ['nim', 'nama', 'name', 'email', 'username', 'ref'];

    /**
     * @param list<list<string|null>> $rows
     * @return array{records:list<array>,errors:list<array{row:int,message:string}>,label_counts:array<string,int>}
     */
    public static function validate(array $rows): array
    {
        $fail = fn (string $m) => ['records' => [], 'errors' => [['row' => 1, 'message' => $m]], 'label_counts' => []];

        if ($rows === []) {
            return $fail('Berkas kosong.');
        }
        $header = array_map(function ($h) {
            $h = trim(strtolower(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)));
            $h = trim((string) preg_replace('/[^a-z0-9]+/', '_', $h), '_');

            return self::ALIASES[$h] ?? $h;
        }, array_shift($rows));

        $identity = array_values(array_intersect($header, self::IDENTITY));
        if ($identity !== []) {
            return $fail('Berkas memuat kolom identitas ('.implode(', ', $identity).'). Hapus kolom itu. Data historis harus anonim.');
        }
        $missing = array_values(array_diff(self::REQUIRED, $header));
        if ($missing !== []) {
            return $fail('Kolom wajib tidak ditemukan: '.implode(', ', $missing).'. Pakai templat yang disediakan.');
        }
        if (count($rows) > self::MAX_ROWS) {
            return $fail('Berkas berisi lebih dari '.self::MAX_ROWS.' baris.');
        }
        $col = array_flip($header);

        $records = [];
        $errors = [];
        $counts = array_fill_keys(RiskModel::LEVELS, 0);
        foreach ($rows as $i => $cells) {
            $line = $i + 2;
            if (trim(implode('', array_map('strval', $cells))) === '') {
                continue;
            }
            $num = function (string $k) use ($cells, $col) {
                $raw = trim((string) ($cells[$col[$k]] ?? ''));

                return Grades::parseNumber($k === 'perubahan_ips' ? ltrim($raw, '-') : $raw);
            };
            $neg = str_starts_with(trim((string) ($cells[$col['perubahan_ips']] ?? '')), '-');

            $attendance = $num('kehadiran');
            $ips = $num('ips');
            $ratio = $num('rasio_sks_tidak_lulus');
            $delta = $num('perubahan_ips');
            $label = strtolower(trim((string) ($cells[$col['label']] ?? '')));

            $rowErrors = [];
            if ($attendance === null || $attendance > 100) {
                $rowErrors[] = 'Kehadiran harus 0 sampai 100.';
            }
            if ($ips === null || $ips > 4) {
                $rowErrors[] = 'IPS harus 0 sampai 4.';
            }
            if ($ratio === null || $ratio > 1) {
                $rowErrors[] = 'Rasio SKS tidak lulus harus 0 sampai 1.';
            }
            if ($delta === null || $delta > 4) {
                $rowErrors[] = 'Perubahan IPS harus antara -4 dan 4.';
            }
            if (! in_array($label, RiskModel::LEVELS, true)) {
                $rowErrors[] = 'Label harus rendah, sedang, atau tinggi.';
            }

            if ($rowErrors) {
                foreach ($rowErrors as $m) {
                    $errors[] = ['row' => $line, 'message' => $m];
                }
                if (count($errors) >= 50) {
                    break;
                }

                continue;
            }

            $records[] = [
                'attendance' => $attendance,
                'ips' => $ips,
                'failed_ratio' => $ratio,
                'ips_delta' => $neg ? -$delta : $delta,
                'label' => $label,
            ];
            $counts[$label]++;
        }

        if ($errors) {
            return ['records' => [], 'errors' => $errors, 'label_counts' => []];
        }
        if (count($records) < 100) {
            return $fail('Data historis minimal 100 baris agar ada data latih dan data uji. Berkasmu berisi '.count($records).'.');
        }

        return ['records' => $records, 'errors' => [], 'label_counts' => $counts];
    }
}
