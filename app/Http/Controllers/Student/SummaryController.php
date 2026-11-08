<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicRecord;
use App\Models\Consent;
use App\Models\Reminder;
use App\Models\RiskResult;
use App\Services\AccessLogger;
use App\Services\RiskService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    public function show(Request $request, RiskService $risk): View
    {
        $user = $request->user();
        $ref = $user->student_ref;

        $hasData = AcademicRecord::where('student_ref', $ref)->exists();
        $results = RiskResult::where('student_ref', $ref)->orderBy('semester')->get();
        if ($hasData && $results->isEmpty()) {
            $risk->refresh($ref);
            $results = RiskResult::where('student_ref', $ref)->orderBy('semester')->get();
        }

        $latest = $results->last();
        if ($latest) {
            AccessLogger::record($user, $ref, 'lihat_hasil');
        }

        $reminders = Reminder::where('student_ref', $ref)->latest('id')->limit(4)->get();
        $unreadIds = $reminders->whereNull('read_at')->pluck('id')->all();
        if ($unreadIds) {
            Reminder::whereIn('id', $unreadIds)->update(['read_at' => now()]);
        }

        $consent = $user->dpa_id
            ? Consent::where('student_ref', $ref)->where('dpa_id', $user->dpa_id)->first()
            : null;

        return view('student.summary', [
            'user' => $user,
            'hasData' => $hasData,
            'results' => $results,
            'latest' => $latest,
            'version' => $latest?->modelVersion,
            'reminders' => $reminders,
            'unreadIds' => $unreadIds,
            'dpa' => $user->dpa,
            'consent' => $consent,
        ]);
    }

    public function recalculate(Request $request, RiskService $risk): RedirectResponse
    {
        $result = $risk->refresh($request->user()->student_ref, true);

        return redirect()->route('mhs.ringkasan')->with('status', $result
            ? 'Risiko dihitung ulang dengan data dan model terbaru.'
            : 'Belum ada data akademik. Impor datamu dulu.');
    }
}
