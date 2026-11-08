<?php

namespace Tests\Feature;

use App\Mail\ReminderMail;
use App\Models\ModelVersion;
use App\Models\Reminder;
use App\Models\StudentSetting;
use App\Models\User;
use App\Services\Pseudonym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderEmailTest extends TestCase
{
    use RefreshDatabase;

    private function student(?string $email = null): User
    {
        ModelVersion::create([
            'version' => 'aturan-v1', 'algorithm' => 'Aturan berbobot',
            'params' => ['sedang' => 2.5, 'tinggi' => 5.0, 'engine' => 'rule'],
            'meets_target' => true, 'is_active' => true,
        ]);

        return User::create([
            'name' => 'Mahasiswa Uji', 'username' => '2303045999', 'email' => $email, 'role' => 'mahasiswa',
            'password' => 'password123', 'student_ref' => Pseudonym::ref('2303045999'), 'is_active' => true,
        ]);
    }

    private function riskyCsv(): UploadedFile
    {
        $csv = "semester,kode_mk,nama_mk,sks,nilai,kehadiran_persen\n"
            ."5,IF501,Big Data,3,D,60\n5,IF502,Keamanan Informasi,3,C,65\n5,IF503,Pemrograman Lanjut,3,E,58\n";

        return UploadedFile::fake()->createWithContent('data.csv', $csv);
    }

    public function test_menyimpan_email_mengirim_konfirmasi_ke_alamat_yang_diisi(): void
    {
        Mail::fake();
        $student = $this->student();

        $this->actingAs($student)->post(route('mhs.privasi.pengingat'), [
            'remind_in_app' => 1,
            'remind_email' => 1,
            'email' => 'tujuan@example.com',
        ])->assertRedirect(route('mhs.privasi'))->assertSessionHas('status');

        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo('tujuan@example.com') && $m->kind === ReminderMail::KIND_CONFIRM);
        $this->assertSame('tujuan@example.com', $student->fresh()->email);
        $this->assertTrue(StudentSetting::where('student_ref', $student->student_ref)->value('remind_email'));
    }

    public function test_simpan_ulang_tanpa_perubahan_tidak_mengirim_konfirmasi_lagi(): void
    {
        Mail::fake();
        $student = $this->student();
        $payload = ['remind_in_app' => 1, 'remind_email' => 1, 'email' => 'tujuan@example.com'];

        $this->actingAs($student)->post(route('mhs.privasi.pengingat'), $payload);
        $this->actingAs($student)->post(route('mhs.privasi.pengingat'), $payload);

        Mail::assertSent(ReminderMail::class, 1);
    }

    public function test_email_wajib_diisi_kalau_pengingat_email_dicentang(): void
    {
        Mail::fake();
        $student = $this->student();

        $this->actingAs($student)->post(route('mhs.privasi.pengingat'), [
            'remind_email' => 1,
            'email' => '',
        ])->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_pengingat_risiko_dikirim_ke_email_pengguna_saat_risiko_tinggi(): void
    {
        Mail::fake();
        $student = $this->student('tujuan@example.com');
        StudentSetting::create(['student_ref' => $student->student_ref, 'remind_in_app' => true, 'remind_email' => true]);

        $this->actingAs($student)->post(route('mhs.impor.store'), ['file' => $this->riskyCsv()])
            ->assertRedirect(route('mhs.ringkasan'));

        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo('tujuan@example.com')
            && $m->kind === ReminderMail::KIND_RISK
            && $m->result?->level === 'tinggi');
        $this->assertSame(1, Reminder::where('student_ref', $student->student_ref)->count());
    }

    public function test_email_tidak_dikirim_kalau_pengingat_email_dimatikan(): void
    {
        Mail::fake();
        $student = $this->student('tujuan@example.com');
        StudentSetting::create(['student_ref' => $student->student_ref, 'remind_in_app' => true, 'remind_email' => false]);

        $this->actingAs($student)->post(route('mhs.impor.store'), ['file' => $this->riskyCsv()]);

        Mail::assertNothingSent();
        $this->assertSame(1, Reminder::where('student_ref', $student->student_ref)->count());
    }

    public function test_isi_email_memuat_nama_level_dan_tautan_ringkasan(): void
    {
        $student = $this->student('tujuan@example.com');
        $this->actingAs($student)->post(route('mhs.impor.store'), ['file' => $this->riskyCsv()]);
        $result = \App\Models\RiskResult::where('student_ref', $student->student_ref)->firstOrFail();

        $html = (new ReminderMail($student, ReminderMail::KIND_RISK, $result))->render();

        $this->assertStringContainsString('Mahasiswa Uji', $html);
        $this->assertStringContainsString('tinggi', $html);
        $this->assertStringContainsString(route('mhs.ringkasan'), $html);
    }
}
