<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HistoricalDataset;
use App\Models\HistoricalRecord;
use App\Services\SheetReader;
use App\Support\HistoricalSheet;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class HistoricalDataController extends Controller
{
    public function index(): View
    {
        return view('admin.historical', ['datasets' => HistoricalDataset::latest('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:4096']], [
            'file.required' => 'Pilih berkas CSV dulu.',
            'file.max' => 'Ukuran berkas maksimal 4 MB.',
        ]);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt', 'xlsx'], true)) {
            return back()->with('import_errors', [['row' => 1, 'message' => 'Format berkas harus CSV atau XLSX.']]);
        }

        try {
            $rows = SheetReader::read($file->getRealPath(), $ext, HistoricalSheet::MAX_ROWS + 5);
        } catch (Throwable) {
            return back()->with('import_errors', [['row' => 1, 'message' => 'Berkas tidak bisa dibaca.']]);
        }

        $checked = HistoricalSheet::validate($rows);
        if ($checked['errors']) {
            return back()->with('import_errors', $checked['errors']);
        }

        DB::transaction(function () use ($checked, $file, $request) {
            $dataset = HistoricalDataset::create([
                'name' => mb_substr($file->getClientOriginalName(), 0, 120),
                'row_count' => count($checked['records']),
                'label_counts' => $checked['label_counts'],
                'uploaded_by' => $request->user()->id,
            ]);
            foreach (array_chunk($checked['records'], 500) as $chunk) {
                HistoricalRecord::insert(array_map(fn ($r) => $r + ['historical_dataset_id' => $dataset->id], $chunk));
            }
        });

        return redirect()->route('admin.historis')->with('status', count($checked['records']).' baris data historis disimpan.');
    }

    public function destroy(HistoricalDataset $dataset): RedirectResponse
    {
        $dataset->delete();

        return redirect()->route('admin.historis')->with('status', 'Dataset dihapus.');
    }

    public function template(Request $request): StreamedResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        mt_srand(2026);
        $profiles = [
            'rendah' => [91, 5, 3.35, 0.25, 0.00, 0.05, 0.12, 0.20],
            'sedang' => [79, 6, 2.45, 0.28, 0.05, 0.20, -0.12, 0.25],
            'tinggi' => [66, 8, 1.85, 0.30, 0.20, 0.50, -0.40, 0.30],
        ];

        $rows = [['kehadiran', 'ips', 'rasio_sks_tidak_lulus', 'perubahan_ips', 'label']];
        for ($i = 0; $i < 150; $i++) {
            $label = $i < 60 ? 'rendah' : ($i < 110 ? 'sedang' : 'tinggi');
            [$am, $as, $im, $is, $fl, $fh, $dm, $ds] = $profiles[$label];

            $u = 1 - mt_rand() / mt_getrandmax();
            $v = mt_rand() / mt_getrandmax();
            $z = sqrt(-2 * log($u)) * cos(2 * M_PI * $v);

            $att = round(max(40, min(100, $am + $as * $z)), 1);
            $ips = round(max(0.8, min(4.0, $im + $is * $z)), 2);
            $ratio = round($fl + ($fh - $fl) * (mt_rand() / mt_getrandmax()), 2);
            $delta = round(max(-2.5, min(2.0, $dm + $ds * $z)), 2);

            $rows[] = [$att, $ips, $ratio, $delta, $label];
        }

        if ($request->query('format') === 'xlsx') {
            $tmpFile = tempnam(sys_get_temp_dir(), 'tpl_hist_') . '.xlsx';
            $writer = new \OpenSpout\Writer\XLSX\Writer();
            $writer->openToFile($tmpFile);
            foreach ($rows as $row) {
                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($row));
            }
            $writer->close();

            return response()->download($tmpFile, 'templat-data-historis.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        $csv = "\xEF\xBB\xBF" . implode("\n", array_map(fn ($r) => implode(',', $r), $rows)) . "\n";

        return response()->streamDownload(fn () => print ($csv), 'templat-data-historis.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}