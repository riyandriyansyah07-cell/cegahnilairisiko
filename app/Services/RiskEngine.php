<?php

namespace App\Services;

use App\Models\ModelVersion;
use App\Support\RiskModel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Proses 3.0: menghitung tingkat risiko, skor, dan faktor penyebab.
 *
 * Model aktif dari D5 menentukan jalurnya. Versi ML (Random Forest atau XGBoost dengan SHAP)
 * dihitung oleh layanan Python. Bila layanan mati, atau versi aktifnya model aturan,
 * model aturan berbobot di PHP dipakai sebagai cadangan sesuai rancangan.
 */
class RiskEngine
{
    private const FEATURES = ['attendance', 'ips', 'failed_ratio', 'ips_delta'];

    /** Nama fitur layanan -> kunci faktor pada RiskModel. */
    private const FACTOR_KEYS = ['attendance' => 'attendance', 'ips' => 'ips', 'failed_ratio' => 'failed', 'ips_delta' => 'trend'];

    public function __construct(private ModelService $models) {}

    /**
     * @param list<array{attendance:float,ips:float,failed_ratio:float,ips_delta:float}> $featureSets
     * @return list<array{score:float,level:string,factors:array,model_version_id:int|null,explanation_unavailable?:bool}>
     */
    public function assessMany(array $featureSets): array
    {
        $active = $this->models->active();
        if ($active && $this->models->isMl($active) && config('risk.engine') === 'http') {
            $remote = $this->remote($active, $featureSets);
            if ($remote !== null) {
                return $remote;
            }
        }

        return $this->rule($featureSets);
    }

    public function assess(array $features): array
    {
        return $this->assessMany([$features])[0];
    }

    private function rule(array $featureSets): array
    {
        $version = $this->models->ruleModel();
        $thresholds = ModelService::thresholdsOf($version);

        return array_map(function (array $f) use ($version, $thresholds) {
            $score = RiskModel::score($f);

            return [
                'score' => $score,
                'level' => RiskModel::classify($score, $thresholds),
                'factors' => RiskModel::factors($f),
                'model_version_id' => $version?->id,
                'explanation_unavailable' => false,
            ];
        }, $featureSets);
    }

    /** @return list<array>|null null bila layanan gagal atau jawabannya tidak utuh */
    private function remote(ModelVersion $version, array $featureSets): ?array
    {
        try {
            $results = $this->models->post('/predict', [
                'version' => $version->params['artifact'] ?? $version->version,
                'instances' => array_map(fn (array $f) => Arr::only($f, self::FEATURES), $featureSets),
            ], (int) config('risk.service_timeout'))->throw()->json('results');
        } catch (Throwable $e) {
            Log::warning('Layanan model tidak menjawab, memakai model aturan: '.$e->getMessage());

            return null;
        }

        if (! is_array($results) || count($results) !== count($featureSets)) {
            return null;
        }

        $out = [];
        foreach (array_values($results) as $i => $r) {
            if (! in_array($r['level'] ?? null, RiskModel::LEVELS, true) || ! is_numeric($r['score'] ?? null)) {
                return null;
            }
            $hasContributions = isset($r['contributions']) && is_array($r['contributions']);
            $out[] = [
                'score' => round((float) $r['score'], 2),
                'level' => $r['level'],
                'factors' => $hasContributions
                    ? RiskModel::explain($featureSets[$i], $this->points($r['contributions']))
                    : [],
                'model_version_id' => $version->id,
                'explanation_unavailable' => ! $hasContributions,
            ];
        }

        return $out;
    }

    /** Nilai SHAP per fitur -> poin per kunci faktor. */
    private function points(array $contributions): array
    {
        $points = [];
        foreach (self::FACTOR_KEYS as $feature => $key) {
            $points[$key] = round((float) ($contributions[$feature] ?? 0), 2);
        }

        return $points;
    }
}
