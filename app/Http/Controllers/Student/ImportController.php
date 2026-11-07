<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Services\ImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Smalot\PdfParser\Parser;

class ImportController extends Controller
{
    public function show(Request $request): View
    {
        return view('student.import', [
            'batches' => ImportBatch::where('student_ref', $request->user()->student_ref)->latest('id')->limit(5)->get(),
            'maxKb' => config('risk.import_max_kb'),
        ]);
    }

    public function store(Request $request, ImportService $import): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.config('risk.import_max_kb')],
        ], [
            'file.required' => 'Pilih berkas CSV, XLSX, atau PDF dulu.',
            'file.max' => 'Ukuran berkas maksimal 2 MB.',
        ]);

        $result = $import->handle($request->user(), $request->file('file'));

        if (! $result['ok']) {
            return redirect()->route('mhs.impor')->with('import_errors', $result['errors']);
        }

        return redirect()->route('mhs.ringkasan')->with(
            'status',
            'Impor berhasil: '.$result['rows'].' mata kuliah dari semester '.implode(', ', $result['semesters']).'. Risiko sudah dihitung.'
        );
    }

    /** Fitur Konversi PDF KHS langsung ke format template Excel/CSV untuk diunduh */
    public function convertPdfToExcel(Request $request): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        $request->validate([
            'pdf_file' => ['required', 'file', 'mimes:pdf', 'max:2048'],
        ], [
            'pdf_file.required' => 'Pilih file PDF KHS terlebih dahulu.',
            'pdf_file.mimes' => 'Format file harus berupa PDF.',
            'pdf_file.max' => 'Ukuran file PDF maksimal 2 MB.',
        ]);

        $file = $request->file('pdf_file');
        
        try {
            $rows = $this->extractRowsFromPdf($file->getRealPath(), $request->user()->username);
        } catch (\Throwable $e) {
            return back()->withErrors(['pdf_file' => 'Gagal membaca PDF: '.$e->getMessage()]);
        }

        if (empty($rows) || count($rows) <= 1) {
            return back()->withErrors(['pdf_file' => 'Tidak dapat mengenali baris mata kuliah dari file PDF tersebut.']);
        }

        $format = $request->query('format', 'xlsx');

        if ($format === 'xlsx') {
            $tmpFile = tempnam(sys_get_temp_dir(), 'conv_') . '.xlsx';
            $writer = new XlsxWriter();
            $writer->openToFile($tmpFile);
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
            $writer->close();

            return response()->download($tmpFile, 'templat-hasil-konversi-khs.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        $csv = "\xEF\xBB\xBF" . implode("\n", array_map(fn ($r) => implode(',', $r), $rows)) . "\n";

        return response()->streamDownload(fn () => print($csv), 'templat-hasil-konversi-khs.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function extractRowsFromPdf(string $filePath, string $studentUsername): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();
        $lines = explode("\n", $text);

        $rows = [
            ['semester', 'kode_mk', 'nama_mk', 'sks', 'nilai', 'kehadiran_persen']
        ];

        $entryYear = 2000 + (int) substr(preg_replace('/[^0-9]/', '', $studentUsername), 0, 2);
        if ($entryYear < 2015 || $entryYear > 2035) {
            $entryYear = 2023;
        }

        $academicYearStart = $entryYear;
        $termType = 'gasal';
        $explicitSemester = null;

        foreach ($lines as $line) {
            $cleanLine = trim((string) preg_replace('/\s+/', ' ', $line));
            if (preg_match('/semester\s*[:\-]?\s*(\d+)/i', $cleanLine, $numSem)) {
                $explicitSemester = (int) $numSem[1];
            }
            if (preg_match('/(?:th|tahun)?\s*akademik.*?(\d{4})/i', $cleanLine, $yrMatch)) {
                $academicYearStart = (int) $yrMatch[1];
            } elseif (preg_match('/(\d{4})\s*\/\s*\d{4}/', $cleanLine, $yrSlash)) {
                $academicYearStart = (int) $yrSlash[1];
            }
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
            if ($line === '') continue;

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
                if ($namaMk === '') $namaMk = $rawName;

                $nilaiHuruf = 'B';
                if ($explicitGrade !== '' && in_array($explicitGrade, $validLetters, true)) {
                    $nilaiHuruf = $explicitGrade;
                } else {
                    preg_match_all('/(\d{2,3}[\.,]?\d*)/', $tail, $numbers);
                    if (!empty($numbers[1])) {
                        $lastNum = (float) str_replace(',', '.', end($numbers[1]));
                        if ($lastNum <= 4.0) $lastNum *= 25;
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
                    if ($val <= 100 && $val >= 0) $kehadiran = (string) $val;
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

    public function template(Request $request): StreamedResponse|BinaryFileResponse
    {
        // Kode template standar...
        $rows = [
            ['semester', 'kode_mk', 'nama_mk', 'sks', 'nilai', 'kehadiran_persen'],
            [1, 'IF101', 'Algoritma dan Pemrograman', 3, 'A', 95],
        ];

        if ($request->query('format') === 'xlsx') {
            $tmpFile = tempnam(sys_get_temp_dir(), 'tpl_mhs_') . '.xlsx';
            $writer = new XlsxWriter();
            $writer->openToFile($tmpFile);
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
            $writer->close();

            return response()->download($tmpFile, 'templat-data-akademik.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        $csv = "\xEF\xBB\xBF" . implode("\n", array_map(fn ($r) => implode(',', $r), $rows)) . "\n";

        return response()->streamDownload(fn () => print ($csv), 'templat-data-akademik.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}