<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

class PdfTranscriptReader
{
    /**
     * Membaca file PDF KHS/Transkrip dan mengekstrak baris mata kuliah secara dinamis.
     */
    public static function read(string $filePath): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        $lines = explode("\n", $text);
        $records = [];
        $currentSemester = 1;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            // Deteksi otomatis jika baris menunjukkan Semester (cth: "Semester 1", "Semester III")
            if (preg_match('/semester\s*[:\-]?\s*(\d+)/i', $line, $matches)) {
                $currentSemester = (int)$matches[1];
                continue;
            }

            // Pola regex fleksibel untuk mendeteksi baris mata kuliah: 
            // Kode MK (2-10 alfanumerik), Nama MK, SKS (angka 1-6), Nilai (A-E / angka), Kehadiran opsional
            // Contoh baris: "IF101 Algoritma dan Pemrograman 3 A 95"
            if (preg_match('/^([A-Z0-9\-]{2,10})\s+(.+?)\s+(\d)\s+([A-E][\+\-]?|\d{1,3})(?:\s+(\d{1,3}))?$/ui', $line, $m)) {
                $records[] = [
                    'semester' => $currentSemester,
                    'course_code' => strtoupper(trim($m[1])),
                    'course_name' => trim($m[2]),
                    'credits' => (int)$m[3],
                    'grade_input' => trim($m[4]),
                    'attendance' => isset($m[5]) ? (float)$m[5] : 85.0, // Default 85% jika tidak ada kolom absensi di transkrip
                ];
            }
        }

        return $records;
    }
}