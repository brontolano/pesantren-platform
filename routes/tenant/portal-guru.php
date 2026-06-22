<?php

declare(strict_types=1);

use App\Http\Controllers\PortalGuru\PortalGuruBerandaController;
use App\Http\Controllers\PortalGuru\PortalGuruDisiplinController;
use App\Http\Controllers\PortalGuru\PortalGuruGerbangController;
use App\Http\Controllers\PortalGuru\PortalGuruIzinController;
use App\Http\Controllers\PortalGuru\PortalGuruJurnalController;
use App\Http\Controllers\PortalGuru\PortalGuruKitabController;
use App\Http\Controllers\PortalGuru\PortalGuruNilaiController;
use App\Http\Controllers\PortalGuru\PortalGuruPesanWaliController;
use App\Http\Controllers\PortalGuru\PortalGuruPresensiController;
use App\Http\Controllers\PortalGuru\PortalGuruPwaController;
use App\Http\Controllers\PortalGuru\PortalGuruSetoranController;
use App\Http\Controllers\PortalGuru\PortalGuruSlipController;
use App\Http\Controllers\PortalGuru\PortalGuruTahfidzLanjutanController;
use App\Http\Controllers\PortalGuru\PortalGuruUjianController;
use App\Http\Controllers\PortalGuru\PortalGuruWaliKelasController;
use Illuminate\Support\Facades\Route;

// F1-PWA — PWA Guru (/guru): aset PWA publik (manifest/SW/ikon, pola
// PortalWaliPwaController) + layar inti di balik auth + can:akses_pwa_guru.
Route::prefix('guru')->name('tenant.portal-guru.pwa.')->group(function () {
    Route::get('/manifest.json', [PortalGuruPwaController::class, 'manifest'])->name('manifest');
    Route::get('/icon.svg', [PortalGuruPwaController::class, 'icon'])->name('icon');
    Route::get('/sw.js', [PortalGuruPwaController::class, 'serviceWorker'])->name('service-worker');
});

Route::middleware(['auth', 'can:akses_pwa_guru'])->prefix('guru')->name('tenant.portal-guru.')->group(function () {
    Route::get('/', PortalGuruBerandaController::class)->name('beranda');
    Route::get('/presensi/{jadwal}', [PortalGuruPresensiController::class, 'show'])->name('presensi.show');
    Route::post('/presensi/{jadwal}', [PortalGuruPresensiController::class, 'store'])->name('presensi.store');
    Route::get('/setoran', [PortalGuruSetoranController::class, 'index'])->name('setoran.index');
    Route::get('/setoran/{halaqah}', [PortalGuruSetoranController::class, 'form'])->name('setoran.form');
    Route::post('/setoran', [PortalGuruSetoranController::class, 'store'])->name('setoran.store');

    // WP-11 — mode muhaffizh: sima'an & munaqasyah (idempoten client_ref).
    Route::get('/simaan', [PortalGuruTahfidzLanjutanController::class, 'simaanForm'])->name('simaan.form');
    Route::post('/simaan', [PortalGuruTahfidzLanjutanController::class, 'simaanStore'])->name('simaan.store');
    Route::get('/munaqasyah', [PortalGuruTahfidzLanjutanController::class, 'munaqasyahForm'])->name('munaqasyah.form');
    Route::post('/munaqasyah', [PortalGuruTahfidzLanjutanController::class, 'munaqasyahStore'])->name('munaqasyah.store');

    // WP-24 — PWA Guru Disiplin (kepengasuhan mode)
    Route::get('/disiplin/daftar', [PortalGuruDisiplinController::class, 'daftarPelanggaran'])->name('disiplin.daftar');
    Route::get('/disiplin/catat', [PortalGuruDisiplinController::class, 'formCatatPelanggaran'])->name('disiplin.catat');
    Route::get('/disiplin/poin', [PortalGuruDisiplinController::class, 'poinView'])->name('disiplin.poin');

    // WP-17 — Izin Pegawai Harian (ajukan + antrean approver)
    Route::get('/izin', [PortalGuruIzinController::class, 'index'])->name('izin.index');
    Route::post('/izin', [PortalGuruIzinController::class, 'store'])->name('izin.store');
    Route::post('/izin/{cuti}/setujui', [PortalGuruIzinController::class, 'setujui'])->name('izin.setujui');
    Route::post('/izin/{cuti}/tolak', [PortalGuruIzinController::class, 'tolak'])->name('izin.tolak');

    // F2-PWA — Grid nilai massal (autosave per baris, idempoten upsert)
    Route::get('/nilai', [PortalGuruNilaiController::class, 'index'])->name('nilai.index');
    Route::get('/nilai/{kelas}/{pelajaran}', [PortalGuruNilaiController::class, 'grid'])->name('nilai.grid');
    Route::post('/nilai/{kelas}/{pelajaran}', [PortalGuruNilaiController::class, 'simpanBaris'])->name('nilai.simpan');

    // F2-PWA — Jurnal KBM 30-detik (menempel layar presensi)
    Route::post('/jurnal/{jadwal}', [PortalGuruJurnalController::class, 'store'])->name('jurnal.store');

    // F2-PWA — Capaian kitab (sorogan per santri / bandongan kelompok)
    Route::get('/kitab', [PortalGuruKitabController::class, 'index'])->name('kitab.index');
    Route::get('/kitab/{kajian}', [PortalGuruKitabController::class, 'form'])->name('kitab.form');
    Route::post('/kitab/{kajian}', [PortalGuruKitabController::class, 'store'])->name('kitab.store');

    // F2-PWA — Layar wali kelas (rekap binaan + catatan rapor)
    Route::get('/wali-kelas', [PortalGuruWaliKelasController::class, 'index'])->name('wali-kelas.index');
    Route::get('/wali-kelas/{kelas}', [PortalGuruWaliKelasController::class, 'show'])->name('wali-kelas.show');
    Route::post('/wali-kelas/{kelas}/catatan', [PortalGuruWaliKelasController::class, 'simpanCatatan'])->name('wali-kelas.catatan');

    // WP-29/30 — Pos Gerbang (mode keamanan): scan NIS, catat keluar/
    // masuk, cache izin aktif, registrasi tamu penjengukan.
    Route::get('/gerbang', [PortalGuruGerbangController::class, 'index'])->name('gerbang.index');
    Route::post('/gerbang/catat', [PortalGuruGerbangController::class, 'catat'])->name('gerbang.catat');
    Route::get('/gerbang/izin-aktif', [PortalGuruGerbangController::class, 'izinAktif'])->name('gerbang.izin-aktif');
    Route::post('/gerbang/jenguk/{pengajuan}/hadir', [PortalGuruGerbangController::class, 'jengukHadir'])->name('gerbang.jenguk.hadir');
    Route::post('/gerbang/jenguk/walk-in', [PortalGuruGerbangController::class, 'jengukWalkIn'])->name('gerbang.jenguk.walk-in');

    // F3-PWA — Pesan ke wali santri (binaan saja, idempoten client_ref)
    Route::get('/pesan', [PortalGuruPesanWaliController::class, 'index'])->name('pesan.index');
    Route::post('/pesan', [PortalGuruPesanWaliController::class, 'store'])->name('pesan.store');

    // F3-PWA — Slip gaji milik sendiri (cetak reuse tenant.payroll.slip.print)
    Route::get('/slip-saya', [PortalGuruSlipController::class, 'index'])->name('slip.index');

    // F4-PWA — Ujian: daftar sesi, mode pengawas (polling), koreksi essay,
    // tarik nilai final → komponen nilai (idempoten).
    Route::get('/ujian', [PortalGuruUjianController::class, 'index'])->name('ujian.index');
    Route::get('/ujian/koreksi', [PortalGuruUjianController::class, 'koreksi'])->name('ujian.koreksi');
    Route::post('/ujian/koreksi/{attempt}', [PortalGuruUjianController::class, 'koreksiSimpan'])->name('ujian.koreksi.simpan');
    Route::get('/ujian/{sesi}/pengawas', [PortalGuruUjianController::class, 'pengawas'])->name('ujian.pengawas');
    Route::get('/ujian/{sesi}/pengawas/data', [PortalGuruUjianController::class, 'pengawasData'])->name('ujian.pengawas.data');
    Route::post('/ujian/{sesi}/tarik-nilai', [PortalGuruUjianController::class, 'tarikNilai'])->name('ujian.tarik-nilai');
});
