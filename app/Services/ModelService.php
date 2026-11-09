<?php

namespace App\Services;

use App\Models\AcademicRecord;
use App\Models\HistoricalDataset;
use App\Models\ModelVersion;
use App\Support\RiskModel;
use App\Support\ThresholdTrainer;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** Proses 7.0 dan akses model aktif (D5): melatih, membandingkan, dan mengaktifkan versi model. */
class ModelService
{
    private const ALGORITHMS = [
        'random_forest' => 'Random Forest + SHAP',
        'xgboost' => 'XGBoost + SHAP',
    ];

    private ?ModelVersion $cached = null;

    private bool $loaded = false;

    public function active(): ?ModelVersion
    {
        if (! $this->loaded) {
            $this->cached = ModelVersion::where('is_active', true)->latest('id')->first();
            $this->loaded = true;
        }

        return $this->cached;
    }

    public function isMl(ModelVersion $version): bool
    {
        return ($version->params['engine'] ?? 'rule') === 'ml';
    }

    /** Model aturan untuk perhitungan cadangan: versi aktif bila berupa aturan, jika tidak versi aturan terbaru yang lolos target. */
    public function ruleModel(): ?ModelVersion
    {
        $active = $this->active();
        if ($active && ! $this->isMl($active)) {
            return $active;
        }

        return ModelVersion::where('meets_target', true)->orderByDesc('id')->get()
            ->first(fn (ModelVersion $v) => ! $this->isMl($v));
    }

    /** @return array{sedang:float,tinggi:float} */
    public static function thresholdsOf(?ModelVersion $version): array
    {
        $p = $version?->params;

        return [
            'sedang' => (float) ($p['sedang'] ?? RiskModel::DEFAULT_THRESHOLDS['sedang']),
            'tinggi' => (float) ($p['tinggi'] ?? RiskModel::DEFAULT_THRESHOLDS['tinggi']),
        ];
    }

    /**
     * Latih versi baru (belum aktif) pada satu dataset historis.
     *
     * @throws InvalidArgumentException bila data tidak layak dilatih
     */
    public function train(HistoricalDataset $dataset): ModelVersion
    {
        $rows = $dataset->records()->orderBy('id')->get()->map(fn ($r) => [
            'attendance' => (float) $r->attendance,
            'ips' => (float) $r->ips,
            'failed_ratio' => (float) $r->failed_ratio,
            'ips_delta' => (float) $r->ips_delta,
            'label' => $r->label,
        ])->all();

        $nextNumber = (int) ModelVersion::max('id') + 1;
        $name = (string) $nextNumber;

        // Acak urutan baris secara deterministik berdasarkan nomor versi (agar split train/test bervariasi antar versi)
        if ($nextNumber > 1 && count($rows) > 10) {
            mt_srand(crc32('version-seed-' . $name . '-' . $dataset->id));
            shuffle($rows);
            mt_srand(); // Reset seed ke normal
        }

        [$trainRows, $testRows] = ThresholdTrainer::split($rows);
        $rule = ThresholdTrainer::fitOn($trainRows, $testRows);

        $ml = config('risk.engine') === 'http' ? $this->trainRemote('ml-v'.$name, $trainRows, $testRows) : null;

        if ($ml === null) {
            $version = 'aturan-v'.$name;
            $algorithm = 'Aturan berbobot, ambang dikalibrasi';
            $params = $rule['params'] + ['engine' => 'rule'];
            $metrics = $rule['metrics'] + ['dataset' => $dataset->name];
        } else {
            $version = $ml['version'];
            $algorithm = self::ALGORITHMS[$ml['algorithm']] ?? $ml['algorithm'];
            $params = $ml['params'] + ['engine' => 'ml', 'artifact' => $ml['version'], 'algorithm' => $ml['algorithm']];
            $metrics = $ml['metrics'] + [
                'dataset' => $dataset->name,
                'candidates' => $ml['candidates'] ?? [],
                'feature_importance' => $ml['feature_importance'] ?? [],
                'baseline' => ['macro_f1' => $rule['metrics']['macro_f1'], 'recall_high' => $rule['metrics']['recall_high']],
            ];
        }

        $meets = $metrics['macro_f1'] >= config('risk.target_macro_f1') && $metrics['recall_high'] >= config('risk.target_recall_high');

        return ModelVersion::create([
            'version' => $version,
            'algorithm' => $algorithm,
            'params' => $params,
            'macro_f1' => $metrics['macro_f1'],
            'recall_high' => $metrics['recall_high'],
            'metrics' => $metrics,
            'train_rows' => $rule['train_rows'],
            'test_rows' => $rule['test_rows'],
            'meets_target' => $meets,
            'is_active' => false,
            'trained_at' => now(),
        ]);
    }

    public function activate(ModelVersion $version): void
    {
        if (! $version->meets_target) {
            throw new RuntimeException('Metrik versi ini di bawah target. Model aturan yang aktif tetap dipakai.');
        }
        DB::transaction(function () use ($version) {
            ModelVersion::where('is_active', true)->update(['is_active' => false]);
            $version->update(['is_active' => true]);
        });
        $this->loaded = false;

        // Hitung ulang semua mahasiswa yang sudah memiliki data akademik agar hasil risiko memakai model terbaru.
        $studentRefs = AcademicRecord::query()->distinct()->pluck('student_ref')->all();
        if ($studentRefs !== []) {
            $riskService = app(RiskService::class);
            foreach ($studentRefs as $ref) {
                $riskService->refresh((string) $ref);
            }
        }
    }

    /** Kirim permintaan REST internal ke layanan model. */
    public function post(string $path, array $payload, int $timeout): Response
    {
        $baseUrl = rtrim(trim((string) config('risk.service_url')), '/');

        // Tambahkan https:// otomatis jika user memasukkan domain Railway tanpa skema http/https
        if ($baseUrl !== '' && ! str_starts_with($baseUrl, 'http://') && ! str_starts_with($baseUrl, 'https://')) {
            $baseUrl = 'https://' . $baseUrl;
        }

        if ($baseUrl === '' || ! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('RISK_SERVICE_URL belum siap atau tidak valid: ' . $baseUrl);
        }

        $token = trim((string) config('risk.service_token'));
        $cleanPath = '/' . ltrim($path, '/');

        return Http::timeout($timeout)->acceptJson()
            ->withHeaders($token !== '' ? ['X-Service-Token' => $token] : [])
            ->post($baseUrl . $cleanPath, $payload);
    }

    /** @return array|null null bila layanan tidak tersedia (model aturan dipakai) */
    private function trainRemote(string $version, array $train, array $test): ?array
    {
        try {
            $res = $this->post('/train', ['version' => $version, 'train' => $train, 'test' => $test], (int) config('risk.train_timeout', 30));
        } catch (Throwable $e) {
            Log::warning('Layanan model tidak tersedia untuk pelatihan: '.$e->getMessage());

            return null;
        }

        if ($res->status() === 422) {
            $detail = $res->json('detail');
            throw new InvalidArgumentException(is_string($detail) ? $detail : 'Layanan model menolak data historis. Periksa isi berkas.');
        }
        $body = $res->json();
        if ($res->failed() || ! is_numeric($body['metrics']['macro_f1'] ?? null) || ! is_numeric($body['metrics']['recall_high'] ?? null)
            || ! is_numeric($body['params']['sedang'] ?? null) || ! is_numeric($body['params']['tinggi'] ?? null)
            || ! is_string($body['algorithm'] ?? null) || ($body['version'] ?? null) !== $version) {
            Log::warning('Layanan model menjawab pelatihan dengan status '.$res->status().'. Memakai model aturan.');

            return null;
        }

        return $body;
    }
}