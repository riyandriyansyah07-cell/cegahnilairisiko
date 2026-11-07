<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HistoricalDataset;
use App\Models\ModelVersion;
use App\Services\ModelService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class ModelController extends Controller
{
    public function index(): View
    {
        return view('admin.model', [
            'versions' => ModelVersion::orderByDesc('id')->get(),
            'datasets' => HistoricalDataset::latest('id')->get(),
            'targetF1' => config('risk.target_macro_f1'),
            'targetRecall' => config('risk.target_recall_high'),
        ]);
    }

    public function train(Request $request, ModelService $models): RedirectResponse
    {
        $data = $request->validate(['dataset_id' => ['required', 'exists:historical_datasets,id']], [
            'dataset_id.required' => 'Pilih dataset historis untuk melatih.',
        ]);

        try {
            $version = $models->train(HistoricalDataset::findOrFail($data['dataset_id']));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['dataset_id' => $e->getMessage()]);
        }

        $note = config('risk.engine') === 'http' && ! $models->isMl($version)
            ? ' Layanan model Python tidak menjawab, jadi yang dilatih model aturan.'
            : '';

        return redirect()->route('admin.model')->with('status', ($version->meets_target
            ? "Versi {$version->version} memenuhi target dan siap diaktifkan."
            : "Versi {$version->version} di bawah target. Model aktif yang sekarang tetap dipakai.").$note);
    }

    public function activate(ModelVersion $version, ModelService $models): RedirectResponse
    {
        try {
            $models->activate($version);
        } catch (RuntimeException $e) {
            return back()->withErrors(['activate' => $e->getMessage()]);
        }

        return redirect()->route('admin.model')->with('status', "Versi {$version->version} aktif dan seluruh hasil risiko mahasiswa telah dihitung ulang.");
    }
}
