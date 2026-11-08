<?php

namespace App\Services;

use App\Mail\ReminderMail;
use App\Models\AcademicRecord;
use App\Models\ImportBatch;
use App\Models\Reminder;
use App\Models\RiskResult;
use App\Models\StudentSetting;
use App\Models\User;
use App\Support\RiskModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RiskService
{
    public function __construct(private RiskEngine $engine) {}

    /**
     * Hitung ulang risiko semua semester milik satu mahasiswa dan kembalikan hasil terbaru.
     *
     * $force = true dipakai tombol "Hitung ulang": pengingat tetap dikirim walau level tidak berubah
     * (email dibatasi per level; lama batas diatur RISK_EMAIL_THROTTLE_HOURS, 0 = tanpa batas).
     */
    public function refresh(string $ref, bool $force = false): ?RiskResult
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

        if ($latest && ($force || $latest->wasRecentlyCreated || $latest->wasChanged('level'))) {
            $this->remind($ref, $latest, $force);
        }

        return $latest;
    }

    private function remind(string $ref, RiskResult $result, bool $force = false): void
    {
        if ($result->level === 'rendah') {
            return;
        }
        $settings = StudentSetting::firstOrCreate(['student_ref' => $ref]);

        $message = 'Hasil semester '.$result->semester.' menunjukkan risiko '.$result->level
            .'. Buka ringkasan risiko untuk melihat penyebab dan saran langkahnya.';

        if ($settings->remind_in_app) {
            // firstOrCreate: Hitung ulang berulang tidak menggandakan pengingat yang sama.
            Reminder::firstOrCreate(
                ['student_ref' => $ref, 'risk_result_id' => $result->id, 'level' => $result->level],
                ['message' => $message]
            );
        }

        if ($settings->remind_email) {
            // Batas email saat dipicu Hitung ulang. Atur lewat RISK_EMAIL_THROTTLE_HOURS (0 = tanpa batas, untuk uji).
            $hours = (int) config('mail.risk_throttle_hours', 24);
            if ($force && $hours > 0 && ! Cache::add('risk-email:'.$ref.':'.$result->level, 1, now()->addHours($hours))) {
                Log::info('Pengingat email dilewati: sudah terkirim dalam '.$hours.' jam terakhir untuk '.$ref);

                return;
            }

            $user = User::where('student_ref', $ref)->first();
            if ($user?->email) {
                try {
                    Mail::to($user->email)->send(new ReminderMail($user, ReminderMail::KIND_RISK, $result));
                    Log::info('Pengingat email terkirim ke '.$user->email);
                } catch (Throwable $e) {
                    Log::warning('Pengingat email gagal dikirim: '.$e->getMessage());
                }
            } else {
                Log::info('Pengingat email dilewati: mahasiswa '.$ref.' belum mengisi email.');
            }
        }
    }
}