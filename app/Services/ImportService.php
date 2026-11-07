<?php

namespace App\Services;

use App\Models\AcademicRecord;
use App\Models\ImportBatch;
use App\Models\User;
use App\Support\SheetValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Smalot\PdfParser\Parser;
use Throwable;

class ImportService
{
    public function __construct(private RiskService $risk) {}

    public function handle(User $student, UploadedFile $file): array
    {
        $fail = fn (string $message) => ['ok' => false, 'errors' => [['row' => 1, 'message' => $message]], 'rows' => 0, 'semesters' => [], 'result' => null];

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt', 'xlsx', 'pdf'], true)) {
            return $fail('Format berkas harus CSV, XLSX, atau PDF.');
        }

        try {
            if ($extension === 'pdf') {
                $rows = $this->parsePdfTranscript($file->getRealPath(), $student->username);
            } else {
                $rows = SheetReader::read($file->getRealPath(), $extension);
            }
        } catch (Throwable $e) {
            return $fail('Gagal memproses berkas: '.$e->getMessage());
        }

        if ($extension === 'pdf' && (empty($rows) || count($rows) <= 1)) {
            return $fail('Berkas PDF tidak berisi baris data mata kuliah yang dapat dikenali.');
        }

        $checked = SheetValidator::validate($rows, $student->username);
        if ($checked['errors'] !== []) {
            return ['ok' => false, 'errors' => $checked['errors'], 'rows' => 0, 'semesters' => [], 'result' => null];
        }

        $ref = $student->student_ref;
        DB::transaction(function () use ($checked, $ref, $file) {
            $batch = ImportBatch::create([
                'student_ref' => $ref,
                'source_name' => mb_substr($file->getClientOriginalName(), 0, 120),
                'rows_valid' => count($checked['records']),
                'semesters' => $checked['semesters'],
            ]);

            AcademicRecord::where('student_ref', $ref)->whereIn('semester', $checked['semesters'])->delete();

            $now = now();
            foreach (array_chunk($checked['records'], 200) as $chunk) {
                AcademicRecord::insert(array_map(fn ($r) => $r + [
                    'student_ref' => $ref,
                    'import_batch_id' => $batch->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }
        });

        return [
            'ok' => true,
            'errors' => [],
            'rows' => count($checked['records']),
            'semesters' => $checked['semesters'],
            'result' => $this->risk->refresh($ref),
        ];
    }

    private function parsePdfTranscript(string $filePath, string $studentUsername = '2303045009'): array
    {
        $header = @file_get_contents($filePath, false, null, 0, 5);
        if ($header !== '%PDF-') {
            throw new \Exception('Berkas yang diunggah bukan merupakan file PDF resmi yang valid.');
        }

        $parser = new Parser;
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        $lines = explode("\n", $text);

        $rows = [
            ['semester', 'kode_mk', 'nama_mk', 'sks', 'nilai', 'kehadiran_persen'],
        ];

        // Ekstraksi Tahun Angkatan dari 2 digit pertama NIM (misal "23" -> 2023)
        $entryYear = 2000 + (int) substr(preg_replace('/[^0-9]/', '', $studentUsername), 0, 2);
        if ($entryYear < 2015 || $entryYear > 2035) {
            $entryYear = 2023;
        }

        $academicYearStart = $entryYear;
        $termType = 'gasal';
        $explicitSemester = null;

        // 1. Deteksi Tahun Akademik & Jenis Semester secara dinamis dan menyeluruh
        foreach ($lines as $line) {
            $cleanLine = trim((string) preg_replace('/\s+/', ' ', $line));

            // Deteksi nomor semester jika tertulis eksplisit angka
            if (preg_match('/semester\s*[:\-]?\s*(\d+)/i', $cleanLine, $numSem)) {
                $explicitSemester = (int) $numSem[1];
            }

            // Deteksi Tahun Akademik (misal "2023/2024" atau "2024 / 2025")
            if (preg_match('/(?:th|tahun)?\s*akademik.*?(\d{4})/i', $cleanLine, $yrMatch)) {
                $academicYearStart = (int) $yrMatch[1];
            } elseif (preg_match('/(\d{4})\s*\/\s*\d{4}/', $cleanLine, $yrSlash)) {
                $academicYearStart = (int) $yrSlash[1];
            }

            // Deteksi Gasal / Genap / Ganjil
            if (preg_match('/(?:semester|periode)?\s*[:\-]?\s*(gasal|ganjil|genap|odd|even)/i', $cleanLine, $termMatch)) {
                $val = strtolower(trim($termMatch[1]));
                if (in_array($val, ['gasal', 'ganjil', 'odd'], true)) {
                    $termType = 'gasal';
                } elseif (in_array($val, ['genap', 'even'], true)) {
                    $termType = 'genap';
                }
            } elseif (preg_match('/^[:\-]?\s*(gasal|ganjil|genap)\b/i', $cleanLine, $kwMatch)) {
                $termType = strtolower($kwMatch[1]);
            }
        }

        // Tentukan nomor semester: Prioritaskan angka eksplisit, atau hitung otomatis berdasarkan angkatan & tahun akademik
        if ($explicitSemester !== null && $explicitSemester >= 1 && $explicitSemester <= 14) {
            $currentSemester = $explicitSemester;
        } else {
            $diffYears = max(0, $academicYearStart - $entryYear);
            $isGasal = in_array($termType, ['gasal', 'ganjil', 'odd'], true);
            $currentSemester = ($diffYears * 2) + ($isGasal ? 1 : 2);
            $currentSemester = max(1, min(14, $currentSemester));
        }

        $validLetters = ['A', 'A-', 'AB', 'B+', 'B', 'B-', 'BC', 'C+', 'C', 'C-', 'D+', 'D', 'E'];

        foreach ($lines as $line) {
            $line = trim((string) preg_replace('/\s+/', ' ', $line));
            if ($line === '') {
                continue;
            }

            if (preg_match('/^\d+\s+([1-6])\s+([A-E][\+\-]?|AB|BC)?\s*([0-9A-Z\-]{5,12})\s+(.+?)\s+([\d\.,]+\s+[\d\.,]+.*)$/ui', $line, $m)) {
                $sks = trim($m[1]);
                $explicitGrade = strtoupper(trim((string) $m[2]));
                $code = trim($m[3]);
                $rawName = trim($m[4]);
                $tail = trim($m[5]);

                if (in_array(strtoupper($code), ['KODE', 'KODEMK', 'MATA', 'MATAKULIAH'], true)) {
                    continue;
                }

                $namaMk = trim((string) preg_replace('/^\d+[\.\-\s]*/', '', $rawName));
                if ($namaMk === '') {
                    $namaMk = $rawName;
                }

                $nilaiHuruf = 'B';
                if ($explicitGrade !== '' && in_array($explicitGrade, $validLetters, true)) {
                    $nilaiHuruf = $explicitGrade;
                } else {
                    preg_match_all('/(\d{2,3}[\.,]?\d*)/', $tail, $numbers);
                    if (!empty($numbers[1])) {
                        $lastNum = (float) str_replace(',', '.', end($numbers[1]));
                        if ($lastNum <= 4.0) {
                            $lastNum *= 25;
                        }
                        if ($lastNum >= 85) $nilaiHuruf = 'A';
                        elseif ($lastNum >= 80) $nilaiHuruf = 'A-';
                        elseif ($lastNum >= 75) $nilaiHuruf = 'B+';
                        elseif ($lastNum >= 70) $nilaiHuruf = 'B';
                        elseif ($lastNum >= 65) $nilaiHuruf = 'B-';
                        elseif ($lastNum >= 60) $nilaiHuruf = 'C+';
                        elseif ($lastNum >= 55) $nilaiHuruf = 'C';
                        elseif ($lastNum >= 40) $nilaiHuruf = 'D';
                        else $nilaiHuruf = 'E';
                    }
                }

                $kehadiran = '85.00';
                if (preg_match_all('/(\d{2,3}[\.,]\d{2})/', $line, $matches)) {
                    $valStr = str_replace(',', '.', end($matches[1]));
                    $val = (float) $valStr;
                    if ($val <= 100 && $val >= 0) {
                        $kehadiran = (string) $val;
                    }
                }

                $rows[] = [
                    (string) $currentSemester,
                    $code,
                    $namaMk,
                    $sks,
                    $nilaiHuruf,
                    $kehadiran,
                ];
            }
        }

        return $rows;
    }
}