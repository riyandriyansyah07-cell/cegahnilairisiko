# Cegah Sebelum Terlambat

Sistem peringatan dini akademik berbasis AI untuk mahasiswa FTII UHAMKA. Capstone Project Kelompok 12.
Implementasi dari dokumen Tugas Pertemuan 6 (kebutuhan, arsitektur, use case, DFD Level 0 dan 1).

Stack: Laravel 13, Tailwind CSS 4 (Vite), PostgreSQL (SQLite untuk prototipe), PHP 8.4 atau lebih baru,
layanan model Python (FastAPI, scikit-learn, XGBoost, SHAP) di folder `ml-service`.

## Menjalankan

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Buat database di pgAdmin 4: klik kanan **Databases**, **Create**, **Database**, beri nama `peringatan_dini`.
Lalu isi `DB_USERNAME` dan `DB_PASSWORD` di `.env` sesuai akun PostgreSQL-mu.

```bash
php artisan migrate --seed
npm install
npm run build        # atau npm run dev saat mengembangkan
php artisan serve
```

Buka http://localhost:8000. Halaman Masuk menampilkan akun contoh (hanya saat `APP_ENV=local`).
Kata sandi semua akun contoh: `password123`. Akun admin: `admin`.

Akun mahasiswa pertama (Riyan, 2303045009) sengaja kosong agar alur impor bisa dicoba dari awal.
Pakai tombol **Unduh templat** di halaman Impor untuk berkas contoh.

Sebelum data nyata masuk, isi `PSEUDONYM_SALT` di `.env` dengan string acak panjang dan jangan diubah lagi.
Mengubahnya membuat kode samar lama tidak cocok dengan NIM.

## Alur dan peta ke dokumen

| Proses DFD | Halaman | Kode |
|---|---|---|
| 1.0 Autentikasi dan hak akses | Masuk, Akun dan peran | `AuthController`, `EnsureRole`, `Admin\AccountController` |
| 2.0 Impor dan validasi | Impor data | `ImportService`, `SheetValidator`, `SheetReader` |
| 3.0 Hitung dan tampilkan risiko | Ringkasan risiko | `RiskService`, `RiskEngine`, `RiskModel`, `ml-service/risk_ml.py` |
| 4.0 Persetujuan dan pengingat | Privasi dan izin | `Student\PrivacyController` |
| 5.0 Dasbor DPA | Mahasiswa bimbingan | `Dpa\GuidanceController` |
| 6.0 Riwayat dan catatan akses | Riwayat, Catatan akses | `Student\HistoryController`, `AccessLogController`, `AccessLogger` |
| 7.0 Data historis dan model | Data historis, Model prediksi | `Admin\HistoricalDataController`, `ModelService`, `ThresholdTrainer`, `ml-service` |

Penyimpanan D1 sampai D6 ada di `database/migrations`. Tabel `users` menyimpan akun (D1).
Data akademik, hasil risiko, dan log hanya memuat `student_ref`, yaitu HMAC-SHA256 dari NIM.

Kebutuhan fungsional FR-01 sampai FR-11 tercakup.

## Perhitungan risiko dan model (FR-03, FR-06, FR-11, NFR-02)

Fitur per semester dihitung dari berkas impor: kehadiran tertimbang SKS, IPS, rasio SKS bernilai di bawah C,
dan perubahan IPS dari semester sebelumnya. Dua model menghitungnya:

| Model | Peran | Penjelasan faktor |
|---|---|---|
| Random Forest dan XGBoost (`ml-service`) | Model utama. Dilatih admin pada data historis anonim. Yang terbaik menurut validasi silang disimpan sebagai versi. | SHAP pada skor risiko |
| Aturan berbobot (`RiskModel`) | Pembanding dan cadangan bila layanan Python mati atau tidak ada versi ML aktif | Poin aturan |

Skor risiko 0 sampai 10 = 10 x (0,5 x P(sedang) + P(tinggi)). Dua ambang skor (sedang, tinggi) dikalibrasi pada
prediksi out-of-fold data latih dengan tujuan macro-F1 + 0,5 x recall Tinggi, supaya kelas Tinggi yang jarang tidak
terlewat. Data uji terpisah (30%, pola indeks 7 dari 10) hanya dipakai sekali untuk menilai versi.
Versi hanya bisa diaktifkan bila macro-F1 >= 0,70 dan recall Tinggi >= 0,80 (`config/risk.php`).
Halaman Model prediksi menampilkan matriks kebingungan, kedua kandidat, dan metrik model aturan pada data uji yang sama.

Semua semester seorang mahasiswa dinilai dalam satu permintaan ke layanan model (NFR-04).
Layanan hanya menerima empat angka per semester, tanpa NIM atau nama.

### Menjalankan layanan model

```bash
cd ml-service
python -m venv .venv && source .venv/bin/activate    # Windows: .venv\Scripts\activate
pip install -r requirements.txt
uvicorn main:app --host 127.0.0.1 --port 8001
```

Lalu di `.env` Laravel: `RISK_ENGINE=http` (dan `RISK_SERVICE_URL`, `RISK_SERVICE_TOKEN` bila perlu).
Alurnya: unggah data historis (menu Data historis) -> Latih dan uji (menu Model prediksi) -> Aktifkan versi ->
mahasiswa menekan Hitung ulang di Ringkasan risiko. Format data historis ada di templat unduhan
(`kehadiran, ips, rasio_sks_tidak_lulus, perubahan_ips, label`), minimal 100 baris dan 5 baris per kelas.

## Pengujian

```bash
php artisan test                    # Laravel: unit dan fitur (hak akses, impor, persetujuan DPA, mesin ML dan cadangan)
cd ml-service && python -m pytest   # layanan model: pelatihan, target NFR-02, SHAP aditif, API, token
```

## Catatan keamanan

- Hak akses diperiksa di server pada setiap permintaan (`EnsureRole`, pemeriksaan izin di `GuidanceController`).
- Kata sandi di-hash bcrypt. Login dibatasi 5 percobaan per menit. Sesi kedaluwarsa 30 menit.
- Berkas impor dibaca dari folder sementara PHP dan tidak pernah disimpan. Sel berawalan `=`, `+`, `-`, `@` dinetralkan.
- Pakai HTTPS di produksi.
