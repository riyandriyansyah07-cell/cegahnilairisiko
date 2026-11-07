<?php

namespace Database\Seeders;

use App\Models\AcademicRecord;
use App\Models\Consent;
use App\Models\HistoricalDataset;
use App\Models\HistoricalRecord;
use App\Models\ImportBatch;
use App\Models\ModelVersion;
use App\Models\StudentSetting;
use App\Models\User;
use App\Services\ModelService;
use App\Services\Pseudonym;
use App\Services\RiskService;
use Illuminate\Database\Seeder;

/**
 * Data contoh sintetis. Semua nama, NIM, dan nilai dibuat untuk demonstrasi,
 * bukan data mahasiswa sebenarnya. Kata sandi semua akun contoh: "password123".
 */
class DatabaseSeeder extends Seeder
{
    private const PASSWORD = 'password123';

    public function run(): void
    {
        mt_srand(2026);

        User::create(['name' => 'Sekretariat FTII', 'username' => 'admin', 'email' => 'sekretariat@uhamka.test', 'role' => 'admin', 'password' => self::PASSWORD]);

        $dpaA = User::create(['name' => 'Dr. Hanafi Rahmat, M.Kom.', 'username' => '0312058801', 'role' => 'dpa', 'password' => self::PASSWORD]);
        $dpaB = User::create(['name' => 'Ir. Sulastri Wibisono, M.T.', 'username' => '0318077402', 'role' => 'dpa', 'password' => self::PASSWORD]);

        $this->seedModel();

        // profil: [nama, NIM, DPA, kehadiran, IPS, peluang gagal, tren, izin]
        $students = [
            ['Riyan Adriyansyah', '2303045009', $dpaA, 90, 3.45, 0.00, 0.10, true],
            ['Nadia Putri Maharani', '2303045012', $dpaA, 94, 3.62, 0.00, 0.05, true],
            ['Bagas Setiawan', '2303045018', $dpaA, 71, 2.20, 0.30, -0.35, true],
            ['Salsabila Nurfadilah', '2303045021', $dpaA, 83, 2.70, 0.08, -0.10, true],
            ['Farhan Dwi Prakoso', '2303045025', $dpaA, 64, 1.85, 0.45, -0.55, true],
            ['Citra Anjani Lestari', '2303045027', $dpaA, 88, 3.10, 0.00, 0.05, false],
            ['Muhammad Rafa Kuswanto', '2303045029', $dpaA, 80, 2.55, 0.12, -0.20, false],
            ['Yobi Fazri Rinandi', '2303045007', $dpaB, 91, 3.30, 0.00, 0.15, true],
            ['Aulia Rahmawati', '2303045033', $dpaB, 76, 2.35, 0.20, -0.25, true],
            ['Dimas Aryo Pamungkas', '2303045036', $dpaB, 86, 2.95, 0.04, 0.00, true],
            ['Keisha Zahra Amelia', '2303045040', $dpaB, 69, 2.05, 0.35, -0.45, false],
            ['Reza Fadillah', '2303045044', $dpaB, 92, 3.50, 0.00, 0.20, true],
        ];

        foreach ($students as $i => [$name, $nim, $dpa, $attendance, $ips, $failRate, $trend, $consent]) {
            $user = User::create([
                'name' => $name,
                'username' => $nim,
                'email' => null,
                'role' => 'mahasiswa',
                'password' => self::PASSWORD,
                'student_ref' => Pseudonym::ref($nim),
                'dpa_id' => $dpa->id,
            ]);
            // Data tidak dibuat untuk Riyan: akun demo mahasiswa dimulai kosong agar alur impor bisa dicoba.
            if ($i !== 0) {
                $this->seedAcademic($user->student_ref, $attendance, $ips, $failRate, $trend);
            }
            if ($i !== 0 && $consent) {
                Consent::create(['student_ref' => $user->student_ref, 'dpa_id' => $dpa->id, 'granted' => true, 'changed_at' => now()->subDays(mt_rand(1, 20))]);
            }
            StudentSetting::firstOrCreate(['student_ref' => $user->student_ref]);
        }
    }

    /** Dataset historis sintetis, lalu latih dan aktifkan versi pertama bila memenuhi target. */
    private function seedModel(): void
    {
        $profiles = [
            'tinggi' => [68, 8, 1.9, 0.35, 0.15, 0.5, -0.4, 0.3],
            'sedang' => [79, 6, 2.45, 0.3, 0.0, 0.2, -0.1, 0.3],
            'rendah' => [91, 5, 3.3, 0.3, 0.0, 0.05, 0.1, 0.25],
        ];

        $rows = [];
        for ($i = 0; $i < 400; $i++) {
            $r = mt_rand(1, 100);
            $label = $r <= 22 ? 'tinggi' : ($r <= 57 ? 'sedang' : 'rendah');
            [$am, $as, $im, $is, $fl, $fh, $dm, $ds] = $profiles[$label];
            $rows[] = [
                'attendance' => round(max(30, min(100, $this->gauss($am, $as))), 2),
                'ips' => round(max(0.5, min(4, $this->gauss($im, $is))), 2),
                'failed_ratio' => round($fl + ($fh - $fl) * mt_rand() / mt_getrandmax(), 4),
                'ips_delta' => round(max(-3, min(3, $this->gauss($dm, $ds))), 2),
                'label' => $label,
            ];
        }
        shuffle($rows);

        $counts = array_count_values(array_column($rows, 'label')) + ['rendah' => 0, 'sedang' => 0, 'tinggi' => 0];
        $dataset = HistoricalDataset::create(['name' => 'data-historis-sintetis.csv', 'row_count' => count($rows), 'label_counts' => $counts]);
        foreach (array_chunk($rows, 200) as $chunk) {
            HistoricalRecord::insert(array_map(fn ($r) => $r + ['historical_dataset_id' => $dataset->id], $chunk));
        }

        // Versi awal: ambang bawaan, ditandai aktif agar sistem langsung bisa menghitung.
        $models = app(ModelService::class);
        $trained = $models->train($dataset);
        if ($trained->meets_target) {
            $models->activate($trained);
        } else {
            ModelVersion::create([
                'version' => 'aturan-awal', 'algorithm' => 'Aturan berbobot, ambang bawaan',
                'params' => ['sedang' => 2.5, 'tinggi' => 5.0], 'meets_target' => true, 'is_active' => true, 'trained_at' => now(),
            ]);
        }
    }

    private function seedAcademic(string $ref, float $attendance, float $ips, float $failRate, float $trend): void
    {
        $catalog = [
            ['Algoritma dan Pemrograman', 'IF101', 3], ['Matematika Diskrit', 'IF102', 3], ['Pengantar Sistem Informasi', 'IF103', 2], ['Bahasa Inggris Teknik', 'UM101', 2],
            ['Struktur Data', 'IF201', 3], ['Basis Data', 'IF202', 3], ['Sistem Operasi', 'IF203', 3], ['Statistika', 'UM201', 2],
            ['Pemrograman Web', 'IF301', 3], ['Rekayasa Perangkat Lunak', 'IF302', 3], ['Jaringan Komputer', 'IF303', 3], ['Analisis dan Perancangan Sistem', 'IF304', 3],
            ['Kecerdasan Buatan', 'IF401', 3], ['Interaksi Manusia dan Komputer', 'IF402', 2], ['Manajemen Proyek TI', 'IF403', 3], ['Sistem Pendukung Keputusan', 'IF404', 3],
            ['Big Data', 'IF501', 3], ['Keamanan Informasi', 'IF502', 3], ['Pemrograman Lanjut', 'IF503', 3], ['Tata Kelola TI', 'IF504', 2],
            ['Pemrograman Mobile', 'IF601', 3], ['Data Mining', 'IF602', 3], ['Seminar Capstone Project', 'IF603', 3], ['Etika Profesi', 'UM601', 2],
        ];

        $now = now();
        $batch = ImportBatch::create(['student_ref' => $ref, 'source_name' => 'portal-akademik.csv', 'rows_valid' => 24, 'semesters' => [1, 2, 3, 4, 5, 6]]);
        $records = [];
        foreach ($catalog as $i => [$name, $code, $credits]) {
            $semester = intdiv($i, 4) + 1;
            // tren memengaruhi semester-semester akhir
            $drift = $trend * max(0, $semester - 3) / 3;
            $semIps = max(0.4, min(4.0, $ips + $drift + $this->gauss(0, 0.2)));
            $failed = mt_rand() / mt_getrandmax() < $failRate && $semester >= 5;
            $point = $failed ? [1.0, 1.7][mt_rand(0, 1)] : max(2.0, min(4.0, round($this->gauss($semIps, 0.35) * 3) / 3));
            $att = max(35, min(100, $attendance + $drift * 6 + $this->gauss(0, 4)));

            $records[] = [
                'student_ref' => $ref, 'import_batch_id' => $batch->id, 'semester' => $semester,
                'course_code' => $code, 'course_name' => $name, 'credits' => $credits,
                'grade_point' => round($point, 2), 'grade_input' => $this->letter($point), 'attendance' => round($att, 2),
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        AcademicRecord::insert($records);
        app(RiskService::class)->refresh($ref);
    }

    private function letter(float $p): string
    {
        foreach ([[3.85, 'A'], [3.5, 'A-'], [3.15, 'B+'], [2.85, 'B'], [2.5, 'B-'], [2.15, 'C+'], [1.85, 'C'], [1.35, 'D']] as [$min, $l]) {
            if ($p >= $min) {
                return $l;
            }
        }

        return 'E';
    }

    private function gauss(float $mean, float $sd): float
    {
        $u = 1 - mt_rand() / mt_getrandmax();
        $v = mt_rand() / mt_getrandmax();

        return $mean + $sd * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }
}
