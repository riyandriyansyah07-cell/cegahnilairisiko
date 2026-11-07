<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** UC-07 (proses 6.0): catatan akses. Mahasiswa melihat miliknya sendiri, admin melihat semuanya. */
class AccessLogController extends Controller
{
    public function student(Request $request): View
    {
        return view('student.access-log', [
            'logs' => AccessLog::with('actor')
                ->where('student_ref', $request->user()->student_ref)
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function admin(): View
    {
        return view('admin.access-log', [
            'logs' => AccessLog::with('actor')->orderByDesc('id')->paginate(25),
        ]);
    }
}
