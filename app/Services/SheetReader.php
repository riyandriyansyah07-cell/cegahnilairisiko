<?php

namespace App\Services;

use App\Support\SheetValidator;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/** Membaca CSV atau XLSX menjadi baris teks. Berkas mentah tidak pernah disimpan. */
class SheetReader
{
    /** @return list<list<string>> */
    public static function read(string $path, string $extension, int $limit = SheetValidator::MAX_ROWS + 5): array
    {
        return strtolower($extension) === 'xlsx' ? self::xlsx($path, $limit) : self::csv($path, $limit);
    }

    private static function csv(string $path, int $limit): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = ',';
        $best = substr_count($firstLine, ',');
        foreach ([';', "\t"] as $candidate) {
            if (substr_count($firstLine, $candidate) > $best) {
                $best = substr_count($firstLine, $candidate);
                $delimiter = $candidate;
            }
        }

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(fn ($c) => (string) $c, $row);
            if (count($rows) > $limit) {
                break;
            }
        }
        fclose($handle);

        return $rows;
    }

    private static function xlsx(string $path, int $limit): array
    {
        $reader = new XlsxReader;
        $reader->open($path);
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(function ($c) {
                        if ($c instanceof \DateTimeInterface) {
                            return $c->format('Y-m-d');
                        }

                        return is_scalar($c) || $c === null ? (string) $c : '';
                    }, $row->toArray());
                    if (count($rows) > $limit) {
                        break;
                    }
                }
                break; // hanya lembar pertama
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }
}
