<?php

namespace App\Http\Controllers;

use App\Models\RiskResult;
use App\Support\RiskModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function landing(): View
    {
        // Contoh hasil sintetis untuk pratinjau komponen. Tidak disimpan ke basis data.
        $sample = new RiskResult([
            'semester' => 6,
            'level' => 'sedang',
            'score' => 3.7,
            'features' => ['attendance' => 76.0, 'ips' => 2.41, 'failed_ratio' => 0.1111, 'ips_delta' => -0.18, 'credits' => 18],
            'factors' => RiskModel::factors(['attendance' => 76.0, 'ips' => 2.41, 'failed_ratio' => 0.1111, 'ips_delta' => -0.18]),
            'suggestions' => [],
            'data_as_of' => '2026-10-14',
        ]);
        $sample->suggestions = RiskModel::suggestions('sedang', $sample->factors);

        return view('landing', ['sample' => $sample]);
    }

    /** Arahkan pengguna ke halaman awal sesuai perannya. */
    public function home(Request $request): RedirectResponse
    {
        return redirect()->route(match ($request->user()->role) {
            'dpa' => 'dpa.bimbingan',
            'admin' => 'admin.akun',
            default => 'mhs.ringkasan',
        });
    }
}
