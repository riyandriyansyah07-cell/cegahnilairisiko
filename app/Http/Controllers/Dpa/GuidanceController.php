<?php

namespace App\Http\Controllers\Dpa;

use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\RiskResult;
use App\Models\User;
use App\Services\AccessLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class GuidanceController extends Controller
{
    private const ORDER = ['tinggi' => 0, 'sedang' => 1, 'rendah' => 2, 'belum' => 3];

    /** UC-08 dan UC-14: hanya mahasiswa bimbingan yang menyetujui yang tampil. */
    public function index(Request $request): View
    {
        $dpa = $request->user();
        $students = User::where('dpa_id', $dpa->id)->where('role', User::ROLE_STUDENT)->where('is_active', true)->orderBy('name')->get();

        $granted = Consent::where('dpa_id', $dpa->id)->where('granted', true)->pluck('student_ref')->all();
        $visible = $students->filter(fn ($s) => in_array($s->student_ref, $granted, true))->values();

        $latest = RiskResult::whereIn('student_ref', $visible->pluck('student_ref'))
            ->orderByDesc('semester')->get()->unique('student_ref')->keyBy('student_ref');

        $rows = $visible->map(fn ($s) => [
            'student' => $s,
            'result' => $latest->get($s->student_ref),
            'level' => $latest->get($s->student_ref)?->level ?? 'belum',
        ]);

        $counts = ['semua' => $rows->count()];
        foreach (['tinggi', 'sedang', 'rendah', 'belum'] as $level) {
            $counts[$level] = $rows->where('level', $level)->count();
        }

        $filter = $request->query('tingkat');
        if (in_array($filter, ['tinggi', 'sedang', 'rendah', 'belum'], true)) {
            $rows = $rows->where('level', $filter);
        } else {
            $filter = null;
        }
        $rows = $rows->sortBy(fn ($r) => self::ORDER[$r['level']].'|'.$r['student']->name)->values();

        foreach ($rows as $row) {
            AccessLogger::record($dpa, $row['student']->student_ref, 'daftar_bimbingan');
        }

        return view('dpa.index', [
            'rows' => $rows,
            'counts' => $counts,
            'filter' => $filter,
            'total' => $students->count(),
            'pending' => $students->count() - $visible->count(),
        ]);
    }

    /** UC-09: faktor utama satu mahasiswa. Izin diperiksa ulang pada setiap permintaan. */
    public function show(Request $request, User $student): View
    {
        $dpa = $request->user();
        abort_unless($student->role === User::ROLE_STUDENT && $student->dpa_id === $dpa->id, 403);
        abort_unless(
            Consent::where('student_ref', $student->student_ref)->where('dpa_id', $dpa->id)->where('granted', true)->exists(),
            403,
            'Mahasiswa ini belum memberi izin atau sudah mencabutnya.'
        );

        $results = RiskResult::with('modelVersion')->where('student_ref', $student->student_ref)->orderBy('semester')->get();
        AccessLogger::record($dpa, $student->student_ref, 'detail_faktor');

        return view('dpa.show', ['student' => $student, 'results' => $results, 'latest' => $results->last()]);
    }
}
