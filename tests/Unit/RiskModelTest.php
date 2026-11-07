<?php

namespace Tests\Unit;

use App\Support\Grades;
use App\Support\HistoricalSheet;
use App\Support\RiskModel;
use App\Support\SheetValidator;
use App\Support\ThresholdTrainer;
use PHPUnit\Framework\TestCase;

class RiskModelTest extends TestCase
{
    public function test_skor_dan_tingkat_risiko(): void
    {
        $tinggi = ['attendance' => 70, 'ips' => 2.2, 'failed_ratio' => 0.2, 'ips_delta' => -0.3];
        $aman = ['attendance' => 92, 'ips' => 3.4, 'failed_ratio' => 0, 'ips_delta' => 0.1];

        $this->assertEqualsWithDelta(5.55, RiskModel::score($tinggi), 0.01);
        $this->assertSame('tinggi', RiskModel::classify(RiskModel::score($tinggi), RiskModel::DEFAULT_THRESHOLDS));
        $this->assertSame('rendah', RiskModel::classify(RiskModel::score($aman), RiskModel::DEFAULT_THRESHOLDS));
        $this->assertSame([], RiskModel::factors($aman));
    }

    public function test_konversi_nilai(): void
    {
        $this->assertSame(4.0, Grades::toPoint('A'));
        $this->assertSame(1.3, Grades::toPoint('D+'));
        $this->assertSame(3.3, Grades::toPoint('78,5'));
        $this->assertSame(0.0, Grades::toPoint('3'));
        $this->assertSame(3.0, Grades::toPoint('3', 'bobot'));
        $this->assertNull(Grades::toPoint('Z'));
    }

    public function test_validator_menolak_baris_salah_dan_menetralkan_rumus(): void
    {
        $header = ['semester', 'kode_mk', 'nama_mk', 'sks', 'nilai', 'kehadiran_persen'];

        $bad = SheetValidator::validate([$header, ['x', 'IF1', 'Basis Data', '3', 'Q', '150']], '2303045009');
        $this->assertNotEmpty($bad['errors']);
        $this->assertSame([], $bad['records']);

        $ok = SheetValidator::validate([$header, ['6', 'IF601', '=cmd|calc', '3', 'D+', '60']], '2303045009');
        $this->assertSame([], $ok['errors']);
        $this->assertSame("'=cmd|calc", $ok['records'][0]['course_name']);
        $this->assertSame(1.3, $ok['records'][0]['grade_point']);
    }

    public function test_pelatihan_ambang_memakai_data_uji_terpisah(): void
    {
        mt_srand(7);
        $rows = [];
        for ($i = 0; $i < 200; $i++) {
            $label = ['rendah', 'sedang', 'tinggi'][$i % 3];
            $base = ['rendah' => [92, 3.4, 0.0], 'sedang' => [79, 2.5, 0.1], 'tinggi' => [66, 1.8, 0.35]][$label];
            $rows[] = ['attendance' => $base[0] + mt_rand(-3, 3), 'ips' => $base[1] + mt_rand(-10, 10) / 100,
                'failed_ratio' => $base[2], 'ips_delta' => 0, 'label' => $label];
        }
        $fit = ThresholdTrainer::fit($rows);

        $this->assertSame(140, $fit['train_rows']);
        $this->assertSame(60, $fit['test_rows']);
        $this->assertGreaterThan(0.7, $fit['metrics']['macro_f1']);
    }

    public function test_explain_memakai_poin_luar_untuk_faktor_ml(): void
    {
        $f = ['attendance' => 90.0, 'ips' => 2.2, 'failed_ratio' => 0.2, 'ips_delta' => 0.3];
        $out = RiskModel::explain($f, ['attendance' => 0.4, 'ips' => 1.6, 'failed' => -0.3, 'trend' => 0.2]);

        $this->assertSame(['ips', 'attendance', 'trend'], array_column($out, 'key'));
        $this->assertSame(1.6, $out[0]['points']);
        $this->assertStringContainsString('IPS naik 0,30', $out[2]['detail']);
        $this->assertStringNotContainsString('IPS turun', $out[2]['detail']);
        $this->assertEqualsWithDelta(100.0, array_sum(array_column($out, 'share')), 0.2);
        $this->assertSame([], RiskModel::explain($f, ['attendance' => -1, 'ips' => 0.004]));
    }

    public function test_pembagian_data_latih_uji_dan_data_terlalu_sedikit(): void
    {
        $rows = array_map(fn ($i) => ['attendance' => 80, 'ips' => 3, 'failed_ratio' => 0, 'ips_delta' => 0, 'label' => 'rendah', 'i' => $i], range(0, 99));
        [$train, $test] = ThresholdTrainer::split($rows);

        $this->assertCount(70, $train);
        $this->assertCount(30, $test);
        $this->assertSame([7, 8, 9], array_slice(array_column($test, 'i'), 0, 3));

        $this->expectException(\InvalidArgumentException::class);
        ThresholdTrainer::split(array_slice($rows, 0, 20));
    }

    public function test_data_historis_menolak_kolom_identitas_dan_label_salah(): void
    {
        $header = ['kehadiran', 'ips', 'rasio_sks_tidak_lulus', 'perubahan_ips', 'label'];

        $withId = HistoricalSheet::validate([array_merge($header, ['nim']), ['90', '3', '0', '0', 'rendah', '2303045009']]);
        $this->assertStringContainsString('identitas', $withId['errors'][0]['message']);

        $rows = [$header];
        for ($i = 0; $i < 100; $i++) {
            $rows[] = ['85', '3.1', '0', '-0.2', ['rendah', 'sedang', 'tinggi'][$i % 3]];
        }
        $ok = HistoricalSheet::validate($rows);
        $this->assertSame([], $ok['errors']);
        $this->assertSame(-0.2, $ok['records'][0]['ips_delta']);

        $rows[5][4] = 'kritis';
        $this->assertNotEmpty(HistoricalSheet::validate($rows)['errors']);
    }
}
