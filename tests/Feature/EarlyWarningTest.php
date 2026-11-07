<?php

namespace Tests\Feature;

use App\Models\Consent;
use App\Models\HistoricalDataset;
use App\Models\HistoricalRecord;
use App\Models\ModelVersion;
use App\Models\RiskResult;
use App\Models\User;
use App\Services\ModelService;
use App\Services\Pseudonym;
use App\Services\RiskEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class EarlyWarningTest extends TestCase
{
    use RefreshDatabase;

    private const RISKY = ['attendance' => 62.0, 'ips' => 1.7, 'failed_ratio' => 0.3, 'ips_delta' => -0.5];

    private function ruleVersion(bool $active = true): ModelVersion
    {
        return ModelVersion::create([
            'version' => 'aturan-v1', 'algorithm' => 'Aturan berbobot', 'params' => ['sedang' => 2.5, 'tinggi' => 5.0, 'engine' => 'rule'],
            'meets_target' => true, 'is_active' => $active,
        ]);
    }

    private function mlVersion(bool $active = true): ModelVersion
    {
        return ModelVersion::create([
            'version' => 'ml-v2', 'algorithm' => 'XGBoost + SHAP',
            'params' => ['sedang' => 2.0, 'tinggi' => 5.75, 'engine' => 'ml', 'artifact' => 'ml-v2', 'algorithm' => 'xgboost'],
            'meets_target' => true, 'is_active' => $active,
        ]);
    }

    private function users(): array
    {
        $dpa = User::create(['name' => 'Dosen Uji', 'username' => '0300000001', 'role' => 'dpa', 'password' => 'password123', 'is_active' => true]);
        $student = User::create([
            'name' => 'Mahasiswa Uji', 'username' => '2303045999', 'role' => 'mahasiswa', 'password' => 'password123',
            'student_ref' => Pseudonym::ref('2303045999'), 'dpa_id' => $dpa->id, 'is_active' => true,
        ]);
        $admin = User::create(['name' => 'Admin Uji', 'username' => 'admin', 'role' => 'admin', 'password' => 'password123', 'is_active' => true]);

        return [$student, $dpa, $admin];
    }

    private function csv(): UploadedFile
    {
        $csv = "semester,kode_mk,nama_mk,sks,nilai,kehadiran_persen\n"
            ."5,IF501,Big Data,3,D,60\n5,IF502,Keamanan Informasi,3,C,65\n5,IF503,Pemrograman Lanjut,3,E,58\n";

        return UploadedFile::fake()->createWithContent('data.csv', $csv);
    }

    public function test_hak_akses_menurut_peran(): void
    {
        $this->ruleVersion();
        [$student, $dpa, $admin] = $this->users();

        $this->get(route('mhs.ringkasan'))->assertRedirect(route('login'));
        $this->actingAs($student)->get(route('admin.akun'))->assertForbidden();
        $this->actingAs($student)->get(route('dpa.bimbingan'))->assertForbidden();
        $this->actingAs($admin)->get(route('mhs.ringkasan'))->assertForbidden();
        $this->actingAs($dpa)->get(route('admin.model'))->assertForbidden();
    }

    public function test_impor_menghitung_risiko_dan_dpa_hanya_melihat_yang_menyetujui(): void
    {
        $this->ruleVersion();
        [$student, $dpa] = $this->users();

        $this->actingAs($student)->post(route('mhs.impor.store'), ['file' => $this->csv()])
            ->assertRedirect(route('mhs.ringkasan'));

        $result = RiskResult::where('student_ref', $student->student_ref)->firstOrFail();
        $this->assertSame('tinggi', $result->level);
        $this->assertNotNull($result->model_version_id);

        $this->actingAs($dpa)->get(route('dpa.bimbingan.show', $student))->assertForbidden();

        $this->actingAs($student)->post(route('mhs.privasi.izin'), ['granted' => 1]);
        $this->actingAs($dpa)->get(route('dpa.bimbingan.show', $student))->assertOk();
        $this->actingAs($dpa)->get(route('dpa.bimbingan'))->assertOk()->assertSee('Mahasiswa Uji');

        $this->actingAs($student)->post(route('mhs.privasi.izin'), ['granted' => 0]);
        $this->actingAs($dpa)->get(route('dpa.bimbingan.show', $student))->assertForbidden();
        $this->assertSame(1, Consent::where('granted', false)->count());
    }

    public function test_impor_tidak_valid_tidak_menyimpan_apa_pun(): void
    {
        $this->ruleVersion();
        [$student] = $this->users();
        $bad = UploadedFile::fake()->createWithContent('bad.csv', "semester,kode_mk,nama_mk,sks,nilai,kehadiran_persen\nx,IF1,Basis Data,3,Q,150\n");

        $this->actingAs($student)->post(route('mhs.impor.store'), ['file' => $bad])
            ->assertRedirect(route('mhs.impor'))->assertSessionHas('import_errors');
        $this->assertSame(0, RiskResult::count());
    }

    public function test_mesin_ml_memakai_layanan_dan_faktor_dari_shap(): void
    {
        config(['risk.engine' => 'http']);
        $this->ruleVersion(false);
        $ml = $this->mlVersion();
        Http::fake(['*/predict' => Http::response(['version' => 'ml-v2', 'results' => [[
            'score' => 6.4, 'level' => 'tinggi',
            'probabilities' => ['rendah' => 0.0, 'sedang' => 0.2, 'tinggi' => 0.8],
            'contributions' => ['attendance' => 0.4, 'ips' => 1.6, 'failed_ratio' => -0.2, 'ips_delta' => 0.0],
        ]]])]);

        $out = app(RiskEngine::class)->assess(self::RISKY);

        $this->assertSame('tinggi', $out['level']);
        $this->assertSame($ml->id, $out['model_version_id']);
        $this->assertSame(['ips', 'attendance'], array_column($out['factors'], 'key'));
        $this->assertFalse($out['explanation_unavailable']);
        Http::assertSent(fn ($r) => $r['version'] === 'ml-v2' && ! array_key_exists('credits', $r['instances'][0]));
    }

    public function test_shap_gagal_tidak_mencampur_faktor_model_aturan(): void
    {
        config(['risk.engine' => 'http']);
        $this->ruleVersion(false);
        $ml = $this->mlVersion();
        Http::fake(['*/predict' => Http::response(['version' => 'ml-v2', 'results' => [[
            'score' => 6.4, 'level' => 'tinggi',
            'probabilities' => ['rendah' => 0.0, 'sedang' => 0.2, 'tinggi' => 0.8],
            'contributions' => null,
        ]]])]);

        $out = app(RiskEngine::class)->assess(self::RISKY);

        $this->assertSame('tinggi', $out['level']);
        $this->assertSame($ml->id, $out['model_version_id']);
        $this->assertSame([], $out['factors']);
        $this->assertTrue($out['explanation_unavailable']);
    }

    public function test_layanan_mati_memakai_model_aturan_sebagai_cadangan(): void
    {
        config(['risk.engine' => 'http']);
        $rule = $this->ruleVersion(false);
        $this->mlVersion();
        Http::fake(['*' => Http::response('gagal', 500)]);

        $out = app(RiskEngine::class)->assess(self::RISKY);

        $this->assertSame($rule->id, $out['model_version_id']);
        $this->assertSame('tinggi', $out['level']);
        $this->assertNotEmpty($out['factors']);
    }

    public function test_pencarian_akun_dpa_nonaktif_email_kosong_dan_validasi_dpa(): void
    {
        $this->ruleVersion();
        [$student, $dpa, $admin] = $this->users();

        $this->actingAs($admin)->get(route('admin.akun', ['q' => 'mahasiswa']))->assertOk()->assertSee('Mahasiswa Uji');

        $dpa->update(['is_active' => false]);
        $this->actingAs($admin)->put(route('admin.akun.update', $student), [
            'name' => 'Mahasiswa Uji Baru',
            'is_active' => 1,
        ])->assertRedirect(route('admin.akun'));
        $this->assertSame($dpa->id, $student->fresh()->dpa_id);

        $this->actingAs($student)->post(route('mhs.privasi.pengingat'), [
            'remind_in_app' => 1,
            'remind_email' => 0,
        ])->assertRedirect(route('mhs.privasi'));

        $this->actingAs($admin)->post(route('admin.akun.store'), [
            'name' => 'Mhs Baru',
            'username' => '2303045888',
            'role' => 'mahasiswa',
            'password' => 'password123',
            'dpa_id' => 999999,
        ])->assertSessionHasErrors('dpa_id');

        $this->actingAs($student)->delete('/mahasiswa/riwayat/abc')->assertNotFound();
    }

    public function test_aktivasi_model_baru_menghitung_ulang_hasil_mahasiswa(): void
    {
        $this->ruleVersion(true);
        [$student, , $admin] = $this->users();

        $this->actingAs($student)->post(route('mhs.impor.store'), ['file' => $this->csv()]);
        $newRule = ModelVersion::create([
            'version' => 'aturan-v2', 'algorithm' => 'Aturan berbobot v2',
            'params' => ['sedang' => 2.5, 'tinggi' => 5.0, 'engine' => 'rule'],
            'meets_target' => true, 'is_active' => false,
        ]);

        $this->actingAs($admin)->post(route('admin.model.aktifkan', $newRule))->assertRedirect(route('admin.model'));
        $this->assertSame($newRule->id, RiskResult::where('student_ref', $student->student_ref)->firstOrFail()->model_version_id);
    }

    private function dataset(): HistoricalDataset
    {
        $dataset = HistoricalDataset::create(['name' => 'uji.csv', 'row_count' => 120, 'label_counts' => ['rendah' => 40, 'sedang' => 40, 'tinggi' => 40]]);
        $base = ['rendah' => [92, 3.4, 0.0, 0.1], 'sedang' => [79, 2.5, 0.1, -0.1], 'tinggi' => [64, 1.8, 0.35, -0.5]];
        for ($i = 0; $i < 120; $i++) {
            $label = ['rendah', 'sedang', 'tinggi'][$i % 3];
            [$a, $ips, $fail, $delta] = $base[$label];
            HistoricalRecord::create([
                'historical_dataset_id' => $dataset->id, 'attendance' => $a + ($i % 5), 'ips' => $ips + ($i % 4) / 20,
                'failed_ratio' => $fail, 'ips_delta' => $delta, 'label' => $label,
            ]);
        }

        return $dataset;
    }

    public function test_pelatihan_ml_menyimpan_versi_dengan_pembanding_aturan(): void
    {
        config(['risk.engine' => 'http']);
        Http::fake(['*/train' => Http::response([
            'version' => 'ml-v1', 'algorithm' => 'xgboost', 'params' => ['sedang' => 2.0, 'tinggi' => 5.75],
            'metrics' => ['macro_f1' => 0.88, 'recall_high' => 1.0, 'confusion' => []],
            'candidates' => [], 'feature_importance' => ['ips' => 0.5],
        ])]);

        $version = app(ModelService::class)->train($this->dataset());

        $this->assertSame('ml-v1', $version->version);
        $this->assertSame('XGBoost + SHAP', $version->algorithm);
        $this->assertSame('ml', $version->params['engine']);
        $this->assertTrue($version->meets_target);
        $this->assertFalse($version->is_active);
        $this->assertArrayHasKey('baseline', $version->metrics);
    }

    public function test_pelatihan_tanpa_layanan_jatuh_ke_model_aturan(): void
    {
        config(['risk.engine' => 'http']);
        Http::fake(['*' => Http::response('', 503)]);

        $version = app(ModelService::class)->train($this->dataset());

        $this->assertSame('aturan-v1', $version->version);
        $this->assertSame('rule', $version->params['engine']);
    }

    public function test_pelatihan_ditolak_layanan_menampilkan_pesan(): void
    {
        config(['risk.engine' => 'http']);
        Http::fake(['*' => Http::response(['detail' => 'Kelas tinggi hanya 2 baris pada data latih.'], 422)]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Kelas tinggi');
        app(ModelService::class)->train($this->dataset());
    }

    public function test_versi_di_bawah_target_tidak_bisa_diaktifkan(): void
    {
        $this->ruleVersion();
        [, , $admin] = $this->users();
        $weak = ModelVersion::create(['version' => 'lemah', 'algorithm' => 'x', 'params' => ['sedang' => 2, 'tinggi' => 5], 'meets_target' => false]);

        $this->actingAs($admin)->post(route('admin.model.aktifkan', $weak))->assertSessionHasErrors('activate');
        $this->assertFalse($weak->fresh()->is_active);
    }
}
