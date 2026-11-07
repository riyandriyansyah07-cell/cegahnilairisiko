<?php

namespace App\Services;

use App\Models\AcademicRecord;
use App\Models\ImportBatch;
use App\Models\Reminder;
use App\Models\RiskResult;
use App\Models\StudentSetting;
use App\Models\User;
use App\Support\RiskModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RiskService
{
    public function __construct(private RiskEngine $engine) {}

    /** Hitung ulang risiko semua semester milik satu mahasiswa dan kembalikan hasil terbaru. */
    public function refresh(string $ref): ?RiskResult
    {
        $bySemester = AcademicRecord::where('student_ref', $ref)->get()->groupBy('semester')->sortKeys();
        if ($bySemester->isEmpty()) {
            return null;
        }

        $batch = ImportBatch::where('student_ref', $ref)->latest('id')->first();
        $asOf = ($batch?->created_at ?? now())->toDateString();

        // Fitur tiap semester dihitung dulu, lalu seluruh semester dinilai dalam satu permintaan (NFR-04).
        $features = [];
        $previous = null;
        foreach ($bySemester as $semester => $records) {
            $courses = $records->map(fn ($r) => [
                'credits' => (int) $r->credits,
                'grade_point' => (float) $r->grade_point,
                'attendance' => (float) $r->attendance,
            ])->values()->all();

            $features[$semester] = RiskModel::featuresFrom($courses, $previous);
            $previous = $courses;
        }

        $assessments = $this->engine->assessMany(array_values($features));

        $latest = null;
        foreach (array_keys($features) as $i => $semester) {
            $assessment = $assessments[$i];
            $semesterFeatures = $features[$semester];
            if (! empty($assessment['explanation_unavailable'])) {
                $semesterFeatures['explanation_unavailable'] = true;
            }

            $latest = RiskResult::updateOrCreate(
                ['student_ref' => $ref, 'semester' => $semester],
                [
                    'level' => $assessment['level'],
                    'score' => $assessment['score'],
                    'features' => $semesterFeatures,
                    'factors' => $assessment['factors'],
                    'suggestions' => RiskModel::suggestions($assessment['level'], $assessment['factors']),
                    'data_as_of' => $asOf,
                    'model_version_id' => $assessment['model_version_id'],
                ]
            );
        }

        if ($latest && ($latest->wasRecentlyCreated || $latest->wasChanged('level'))) {
            $this->remind($ref, $latest);
        }

        return $latest;
    }

    private function remind(string $ref, RiskResult $result): void
    {
        if ($result->level === 'rendah') {
            return;
        }
        $settings = StudentSetting::firstOrCreate(['student_ref' => $ref]);

        $message = 'Hasil semester '.$result->semester.' menunjukkan risiko '.$result->level
            .'. Buka ringkasan risiko untuk melihat penyebab dan saran langkahnya.';

        if ($settings->remind_in_app) {
            Reminder::create([
                'student_ref' => $ref,
                'risk_result_id' => $result->id,
                'level' => $result->level,
                'message' => $message,
            ]);
        }

        if ($settings->remind_email) {
            $user = User::where('student_ref', $ref)->first();
            if ($user?->email) {
                try {
                    Mail::raw($message."\n\nHasil ini perkiraan berdasarkan data yang kamu impor, bukan penilaian atas dirimu.", function ($mail) use ($user) {
                        $mail->to($user->email)->subject('Pengingat akademik dari Cegah Sebelum Terlambat');
                    });
                } catch (Throwable $e) {
                    Log::warning('Pengingat email gagal dikirim: '.$e->getMessage());
                }
            }
        }
    }
}
