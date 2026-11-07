<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicRecord;
use App\Models\RiskResult;
use App\Services\RiskService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function show(Request $request): View
    {
        return view('student.history', [
            'results' => RiskResult::with('modelVersion')
                ->where('student_ref', $request->user()->student_ref)
                ->orderByDesc('semester')
                ->get(),
        ]);
    }

    public function destroy(Request $request, int $semester, RiskService $risk): RedirectResponse
    {
        if ($semester < 1 || $semester > 14) {
            abort(404);
        }

        $ref = $request->user()->student_ref;
        AcademicRecord::where('student_ref', $ref)->where('semester', $semester)->delete();
        RiskResult::where('student_ref', $ref)->where('semester', $semester)->delete();

        $risk->refresh($ref);

        return redirect()->route('mhs.riwayat')->with('status', "Data dan riwayat semester {$semester} dihapus.");
    }
}
