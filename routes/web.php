<?php

use App\Http\Controllers\AccessLogController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Dpa;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Student;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'landing'])->name('landing');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'show'])->name('login');
    Route::post('/masuk', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/keluar', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/beranda', [PageController::class, 'home'])->name('beranda');

    // Mahasiswa: UC-02 sampai UC-07
    Route::middleware('role:mahasiswa')->prefix('mahasiswa')->name('mhs.')->group(function () {
        Route::get('/ringkasan', [Student\SummaryController::class, 'show'])->name('ringkasan');
        Route::post('/ringkasan/hitung', [Student\SummaryController::class, 'recalculate'])->name('hitung');

        Route::get('/impor', [Student\ImportController::class, 'show'])->name('impor');
        Route::post('/impor', [Student\ImportController::class, 'store'])->name('impor.store');
        Route::get('/impor/templat', [Student\ImportController::class, 'template'])->name('templat');

        Route::get('/privasi', [Student\PrivacyController::class, 'show'])->name('privasi');
        Route::post('/privasi/izin', [Student\PrivacyController::class, 'consent'])->name('privasi.izin');
        Route::post('/privasi/pengingat', [Student\PrivacyController::class, 'reminders'])->middleware('throttle:6,10')->name('privasi.pengingat');

        Route::get('/riwayat', [Student\HistoryController::class, 'show'])->name('riwayat');
        Route::delete('/riwayat/{semester}', [Student\HistoryController::class, 'destroy'])
            ->whereNumber('semester')
            ->name('riwayat.hapus');
        Route::get('/catatan-akses', [AccessLogController::class, 'student'])->name('catatan');
    });

    // DPA: UC-08, UC-09, UC-14
    Route::middleware('role:dpa')->prefix('dpa')->name('dpa.')->group(function () {
        Route::get('/bimbingan', [Dpa\GuidanceController::class, 'index'])->name('bimbingan');
        Route::get('/bimbingan/{student}', [Dpa\GuidanceController::class, 'show'])
            ->whereNumber('student')
            ->name('bimbingan.show');
    });

    // Admin: UC-07, UC-10 sampai UC-12
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/akun', [Admin\AccountController::class, 'index'])->name('akun');
        Route::get('/akun/baru', [Admin\AccountController::class, 'create'])->name('akun.baru');
        Route::post('/akun', [Admin\AccountController::class, 'store'])->name('akun.store');
        Route::get('/akun/{user}/ubah', [Admin\AccountController::class, 'edit'])
            ->whereNumber('user')
            ->name('akun.ubah');
        Route::put('/akun/{user}', [Admin\AccountController::class, 'update'])
            ->whereNumber('user')
            ->name('akun.update');

        Route::get('/data-historis', [Admin\HistoricalDataController::class, 'index'])->name('historis');
        Route::post('/data-historis', [Admin\HistoricalDataController::class, 'store'])->name('historis.store');
        Route::delete('/data-historis/{dataset}', [Admin\HistoricalDataController::class, 'destroy'])
            ->whereNumber('dataset')
            ->name('historis.hapus');
        Route::get('/data-historis/templat', [Admin\HistoricalDataController::class, 'template'])->name('historis.templat');

        Route::get('/model', [Admin\ModelController::class, 'index'])->name('model');
        Route::post('/model/latih', [Admin\ModelController::class, 'train'])->name('model.latih');
        Route::post('/model/{version}/aktifkan', [Admin\ModelController::class, 'activate'])
            ->whereNumber('version')
            ->name('model.aktifkan');

        Route::get('/catatan-akses', [AccessLogController::class, 'admin'])->name('catatan');
    });
});
