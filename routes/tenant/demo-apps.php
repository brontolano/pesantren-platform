<?php

declare(strict_types=1);

use App\Http\Controllers\DemoApps\HumasDemoController;
use App\Http\Controllers\DemoApps\KeamananDemoController;
use App\Http\Controllers\DemoApps\KepegawaianDemoController;
use App\Http\Controllers\DemoApps\PengabdianDemoController;
use App\Http\Controllers\DemoApps\PengasuhanDemoController;
use App\Http\Controllers\DemoApps\PosKantinDemoController;
use App\Http\Controllers\DemoApps\PpdbPanitiaDemoController;
use App\Http\Controllers\DemoApps\SantriDemoController;
use App\Http\Controllers\DemoApps\TuDemoController;
use App\Http\Controllers\DemoApps\UksDemoController;
use Illuminate\Support\Facades\Route;

/*
 * ── App PWA demo peran (controller-dummy, gate auth saja) ──
 * 9 aplikasi showcase: UKS, TU, Humas, Kepegawaian, PPDB Panitia,
 * POS Kantin, Keamanan, Pengasuhan, Pengabdian. Data dummy inline di
 * controller (nol query) → layar selalu penuh & aman. Auto-login persona
 * via /demo-masuk/{key}. Semua pakai UI kit @/Shared.
 */
Route::middleware('auth')->prefix('uks')->name('tenant.uks.')->group(function () {
    Route::get('/', [UksDemoController::class, 'beranda'])->name('beranda');
    Route::get('/kunjungan', [UksDemoController::class, 'kunjungan'])->name('kunjungan');
    Route::get('/kunjungan/catat', [UksDemoController::class, 'catatKunjungan'])->name('kunjungan.catat');
    Route::get('/obat', [UksDemoController::class, 'obat'])->name('obat');
    Route::get('/rujukan', [UksDemoController::class, 'rujukan'])->name('rujukan');
    Route::get('/riwayat', [UksDemoController::class, 'riwayat'])->name('riwayat');
});

Route::middleware('auth')->prefix('tu')->name('tenant.tu.')->group(function () {
    Route::get('/', [TuDemoController::class, 'beranda'])->name('beranda');
    Route::get('/santri', [TuDemoController::class, 'santri'])->name('santri');
    Route::get('/santri/detail', [TuDemoController::class, 'detailSantri'])->name('detail-santri');
    Route::get('/surat', [TuDemoController::class, 'surat'])->name('surat');
    Route::get('/catat-surat', [TuDemoController::class, 'catatSurat'])->name('catat-surat');
    Route::get('/arsip', [TuDemoController::class, 'arsip'])->name('arsip');
});

Route::middleware('auth')->prefix('humas')->name('tenant.humas.')->group(function () {
    Route::get('/', [HumasDemoController::class, 'beranda'])->name('beranda');
    Route::get('/berita', [HumasDemoController::class, 'berita'])->name('berita');
    Route::get('/berita/{artikel}', [HumasDemoController::class, 'detailArtikel'])->name('detail-artikel');
    Route::get('/galeri', [HumasDemoController::class, 'galeri'])->name('galeri');
    Route::get('/agenda', [HumasDemoController::class, 'agenda'])->name('agenda');
});

Route::middleware('auth')->prefix('kepegawaian')->name('tenant.kepegawaian.')->group(function () {
    Route::get('/', [KepegawaianDemoController::class, 'beranda'])->name('beranda');
    Route::get('/pegawai', [KepegawaianDemoController::class, 'pegawai'])->name('pegawai');
    Route::get('/pegawai/{id}', [KepegawaianDemoController::class, 'detailPegawai'])->name('detail-pegawai');
    Route::get('/absensi', [KepegawaianDemoController::class, 'absensi'])->name('absensi');
    Route::get('/slip', [KepegawaianDemoController::class, 'slip'])->name('slip');
    Route::get('/cuti', [KepegawaianDemoController::class, 'cuti'])->name('cuti');
});

Route::middleware('auth')->prefix('ppdb-panitia')->name('tenant.ppdb-panitia.')->group(function () {
    Route::get('/', [PpdbPanitiaDemoController::class, 'beranda'])->name('beranda');
    Route::get('/pendaftar', [PpdbPanitiaDemoController::class, 'pendaftar'])->name('pendaftar');
    Route::get('/detail', [PpdbPanitiaDemoController::class, 'detail'])->name('detail');
    Route::get('/verifikasi', [PpdbPanitiaDemoController::class, 'verifikasi'])->name('verifikasi');
    Route::get('/keputusan', [PpdbPanitiaDemoController::class, 'keputusan'])->name('keputusan');
    Route::get('/hasil', [PpdbPanitiaDemoController::class, 'hasil'])->name('hasil');
});

Route::middleware('auth')->prefix('pos-kantin')->name('tenant.pos-kantin.')->group(function () {
    Route::get('/', [PosKantinDemoController::class, 'beranda'])->name('beranda');
    Route::get('/kasir', [PosKantinDemoController::class, 'kasir'])->name('kasir');
    Route::get('/tabungan', [PosKantinDemoController::class, 'tabungan'])->name('tabungan');
    Route::get('/stok', [PosKantinDemoController::class, 'stok'])->name('stok');
    Route::get('/riwayat', [PosKantinDemoController::class, 'riwayat'])->name('riwayat');
});

Route::middleware('auth')->prefix('keamanan')->name('tenant.keamanan.')->group(function () {
    Route::get('/', [KeamananDemoController::class, 'beranda'])->name('beranda');
    Route::get('/gerbang', [KeamananDemoController::class, 'gerbang'])->name('gerbang');
    Route::get('/tamu', [KeamananDemoController::class, 'tamu'])->name('tamu');
    Route::get('/log', [KeamananDemoController::class, 'log'])->name('log');
    Route::get('/ronda', [KeamananDemoController::class, 'ronda'])->name('ronda');
});

Route::middleware('auth')->prefix('pengasuhan')->name('tenant.pengasuhan.')->group(function () {
    Route::get('/', [PengasuhanDemoController::class, 'beranda'])->name('beranda');
    Route::get('/presensi', [PengasuhanDemoController::class, 'presensiAsrama'])->name('presensi');
    Route::get('/disiplin', [PengasuhanDemoController::class, 'disiplin'])->name('disiplin');
    Route::get('/disiplin/catat', [PengasuhanDemoController::class, 'catatPelanggaran'])->name('catat');
    Route::get('/izin', [PengasuhanDemoController::class, 'izin'])->name('izin');
});

Route::middleware('auth')->prefix('pengabdian')->name('tenant.pengabdian.')->group(function () {
    Route::get('/', [PengabdianDemoController::class, 'beranda'])->name('beranda');
    Route::get('/jadwal', [PengabdianDemoController::class, 'jadwal'])->name('jadwal');
    Route::get('/presensi', [PengabdianDemoController::class, 'presensi'])->name('presensi');
    Route::get('/laporan', [PengabdianDemoController::class, 'laporan'])->name('laporan');
    Route::get('/profil', [PengabdianDemoController::class, 'profil'])->name('profil');
});

Route::middleware('auth')->prefix('santri-app')->name('tenant.santri-app.')->group(function () {
    Route::get('/', [SantriDemoController::class, 'beranda'])->name('beranda');
    Route::get('/jadwal', [SantriDemoController::class, 'jadwal'])->name('jadwal');
    Route::get('/hafalan', [SantriDemoController::class, 'hafalan'])->name('hafalan');
    Route::get('/keuangan', [SantriDemoController::class, 'keuangan'])->name('keuangan');
    Route::get('/izin', [SantriDemoController::class, 'izin'])->name('izin');
    Route::get('/pengumuman', [SantriDemoController::class, 'pengumuman'])->name('pengumuman');
});
