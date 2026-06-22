<?php

declare(strict_types=1);

use App\Http\Controllers\KepalaKeuangan\KepalaKeuanganApiController;
use App\Http\Controllers\KepalaKeuangan\KepalaKeuanganPwaBrandingController;
use App\Http\Controllers\KepalaKeuangan\KepalaKeuanganPwaController as KepalaKeuanganCtrl;
use App\Http\Controllers\KeuanganApiController;
use App\Http\Controllers\KeuanganPwaController;
use Illuminate\Support\Facades\Route;

// ── PWA Kepala Keuangan (/keuangan) — aset publik + layar ber-auth ──
Route::prefix('keuangan')->name('tenant.kepala-keuangan.pwa.')->group(function () {
    Route::get('/manifest.json', [KepalaKeuanganPwaBrandingController::class, 'manifest'])->name('manifest');
    Route::get('/icon.svg', [KepalaKeuanganPwaBrandingController::class, 'icon'])->name('icon');
    Route::get('/sw.js', [KepalaKeuanganPwaBrandingController::class, 'serviceWorker'])->name('service-worker');
});

Route::middleware(['auth', 'can:manage_keuangan'])->prefix('keuangan')->name('tenant.kepala-keuangan.')->group(function () {
    // 01 Beranda
    Route::get('/', [KepalaKeuanganCtrl::class, 'beranda'])->name('beranda');
    // 02 Penerimaan
    Route::get('/penerimaan/tagihan', [KepalaKeuanganCtrl::class, 'tagihan'])->name('penerimaan.tagihan');
    Route::get('/penerimaan/verifikasi', [KepalaKeuanganCtrl::class, 'verifikasi'])->name('penerimaan.verifikasi');
    Route::get('/penerimaan/loket', [KepalaKeuanganCtrl::class, 'loket'])->name('penerimaan.loket');
    // 03 Kas Harian
    Route::get('/kas-harian/tabungan', [KepalaKeuanganCtrl::class, 'tabungan'])->name('kas-harian.tabungan');
    Route::get('/kas-harian/pos', [KepalaKeuanganCtrl::class, 'pos'])->name('kas-harian.pos');
    // 04 Pengeluaran
    Route::get('/pengeluaran/approval', [KepalaKeuanganCtrl::class, 'approval'])->name('pengeluaran.approval');
    Route::get('/pengeluaran/payroll', [KepalaKeuanganCtrl::class, 'payroll'])->name('pengeluaran.payroll');
    // 05 Ledger
    Route::get('/ledger/jurnal', [KepalaKeuanganCtrl::class, 'jurnal'])->name('ledger.jurnal');
    Route::get('/ledger/anggaran', [KepalaKeuanganCtrl::class, 'anggaran'])->name('ledger.anggaran');
    // 06 Laporan
    Route::get('/laporan', [KepalaKeuanganCtrl::class, 'laporan'])->name('laporan.index');
    Route::get('/laporan/neraca-lr', [KepalaKeuanganCtrl::class, 'neracaLr'])->name('laporan.neraca-lr');
    Route::get('/laporan/arus-kas', [KepalaKeuanganCtrl::class, 'arusKas'])->name('laporan.arus-kas');
    Route::get('/laporan/operasional', [KepalaKeuanganCtrl::class, 'laporanOperasional'])->name('laporan.operasional');
    Route::get('/laporan/dana', [KepalaKeuanganCtrl::class, 'laporanDana'])->name('laporan.dana');
    Route::get('/laporan/neraca-saldo', [KepalaKeuanganCtrl::class, 'laporanNeracaSaldo'])->name('laporan.neraca-saldo');
    Route::get('/laporan/psak109', [KepalaKeuanganCtrl::class, 'laporanPsak109'])->name('laporan.psak109');
    Route::get('/laporan/isak35', [KepalaKeuanganCtrl::class, 'laporanIsak35'])->name('laporan.isak35');
    Route::get('/pajak', [KepalaKeuanganCtrl::class, 'pajak'])->name('pajak.index');
    Route::get('/laporan/rekap-tahunan', [KepalaKeuanganCtrl::class, 'rekapTahunan'])->name('laporan.rekap-tahunan');
});

// API Kepala Keuangan (JSON, lazy-load dari Vue)
Route::middleware(['auth', 'can:manage_keuangan'])->prefix('keuangan/api')->name('tenant.kepala-keuangan.api.')->group(function () {
    Route::get('/beranda', [KepalaKeuanganApiController::class, 'beranda'])->name('beranda');
    Route::get('/tagihan', [KepalaKeuanganApiController::class, 'tagihan'])->name('tagihan');
    Route::get('/pembayaran/pending', [KepalaKeuanganApiController::class, 'pembayaranPending'])->name('pembayaran.pending');
    Route::post('/pembayaran/{pembayaran}/verifikasi', [KepalaKeuanganApiController::class, 'verifikasiPembayaran'])->name('pembayaran.verifikasi');
    Route::post('/pembayaran/{pembayaran}/tolak', [KepalaKeuanganApiController::class, 'tolakPembayaran'])->name('pembayaran.tolak');
    Route::get('/tabungan/ringkasan', [KepalaKeuanganApiController::class, 'tabunganRingkasan'])->name('tabungan.ringkasan');
    Route::get('/jurnal', [KepalaKeuanganApiController::class, 'jurnal'])->name('jurnal');
    Route::get('/neraca-saldo', [KepalaKeuanganApiController::class, 'neracaSaldo'])->name('neraca-saldo');
    Route::get('/anggaran/realisasi', [KepalaKeuanganApiController::class, 'anggaranRealisasi'])->name('anggaran.realisasi');
    Route::get('/laporan/neraca', [KepalaKeuanganApiController::class, 'laporanNeraca'])->name('laporan.neraca');
    Route::get('/laporan/laba-rugi', [KepalaKeuanganApiController::class, 'laporanLabaRugi'])->name('laporan.laba-rugi');
    Route::get('/laporan/arus-kas', [KepalaKeuanganApiController::class, 'laporanArusKas'])->name('laporan.arus-kas');
    Route::get('/laporan/operasional', [KepalaKeuanganApiController::class, 'laporanOperasional'])->name('laporan.operasional');
    Route::get('/laporan/dana', [KepalaKeuanganApiController::class, 'laporanDana'])->name('laporan.dana');
    Route::get('/laporan/tutup-buku', [KepalaKeuanganApiController::class, 'tutupBukuPratinjau'])->name('laporan.tutup-buku.pratinjau');
    Route::post('/laporan/tutup-buku', [KepalaKeuanganApiController::class, 'tutupBuku'])->name('laporan.tutup-buku');
    Route::get('/laporan/psak109', [KepalaKeuanganApiController::class, 'laporanPsak109'])->name('laporan.psak109');
    Route::get('/laporan/isak35', [KepalaKeuanganApiController::class, 'laporanIsak35'])->name('laporan.isak35');
    Route::get('/laporan/rekap-tahunan', [KepalaKeuanganApiController::class, 'rekapTahunan'])->name('laporan.rekap-tahunan');

    // Pajak
    Route::get('/pajak/spt-masa', [KepalaKeuanganApiController::class, 'sptMasa'])->name('pajak.spt-masa');
    Route::get('/pajak/spt-masa-tahunan', [KepalaKeuanganApiController::class, 'sptMasaTahunan'])->name('pajak.spt-masa-tahunan');
    Route::get('/pajak/ebupot', [KepalaKeuanganApiController::class, 'ebupot'])->name('pajak.ebupot');
    Route::get('/pajak/ebupot/csv', [KepalaKeuanganApiController::class, 'ebupotCsv'])->name('pajak.ebupot.csv');
    Route::get('/pajak/ebupot-tahunan', [KepalaKeuanganApiController::class, 'ebupotTahunan'])->name('pajak.ebupot-tahunan');
    Route::get('/pajak/spt-badan', [KepalaKeuanganApiController::class, 'sptBadan'])->name('pajak.spt-badan');
    Route::get('/pajak/sisa-lebih', [KepalaKeuanganApiController::class, 'sisaLebih'])->name('pajak.sisa-lebih');

    // Loket Kas
    Route::get('/loket', [KepalaKeuanganApiController::class, 'loket'])->name('loket');
    Route::post('/loket/buka-sesi', [KepalaKeuanganApiController::class, 'bukaSesiLoket'])->name('loket.buka');
    Route::post('/loket/tutup-sesi', [KepalaKeuanganApiController::class, 'tutupSesiLoket'])->name('loket.tutup');
    Route::post('/loket/terima-tunai', [KepalaKeuanganApiController::class, 'terimaTunai'])->name('loket.terima');

    // POS Kantin
    Route::get('/pos/ringkasan', [KepalaKeuanganApiController::class, 'posRingkasan'])->name('pos.ringkasan');
    Route::get('/pos/produk', [KepalaKeuanganApiController::class, 'posProduk'])->name('pos.produk');
    Route::post('/pos/checkout', [KepalaKeuanganApiController::class, 'posCheckout'])->name('pos.checkout');

    // Approval
    Route::get('/approval', [KepalaKeuanganApiController::class, 'approval'])->name('approval');
    Route::post('/approval/{approval}/setujui', [KepalaKeuanganApiController::class, 'approvalSetujui'])->name('approval.setujui');
    Route::post('/approval/{approval}/tolak', [KepalaKeuanganApiController::class, 'approvalTolak'])->name('approval.tolak');

    // Payroll
    Route::get('/payroll/periode', [KepalaKeuanganApiController::class, 'payrollPeriode'])->name('payroll.periode');
    Route::get('/payroll/slip', [KepalaKeuanganApiController::class, 'payrollSlip'])->name('payroll.slip');
    Route::post('/payroll/proses', [KepalaKeuanganApiController::class, 'payrollProses'])->name('payroll.proses');
});

// PWA Keuangan Lapangan (Vue/Inertia). Memakai API api/keuangan/* yang sudah ada.
Route::middleware('auth')->prefix('admin/keuangan/pwa')->name('tenant.keuangan.pwa.')->group(function () {
    Route::get('/', [KeuanganPwaController::class, 'beranda'])->name('beranda');
    Route::get('/catat-belanja', [KeuanganPwaController::class, 'catatBelanja'])->name('belanja');
    Route::get('/catat-pemasukan', [KeuanganPwaController::class, 'catatPemasukan'])->name('pemasukan');
    Route::get('/dasbor', [KeuanganPwaController::class, 'dasbor'])->name('dasbor');
    Route::get('/slip-saya', [KeuanganPwaController::class, 'slipSaya'])->name('slip');
    Route::get('/setor-keliling', [KeuanganPwaController::class, 'setorKeliling'])->name('setor-keliling');
});

// API PWA Keuangan Lapangan (staf). Tenant di-resolve via web group; auth Sanctum.
Route::middleware(['auth:sanctum'])->prefix('api/keuangan')->name('tenant.api.keuangan.')->group(function () {
    Route::post('/belanja', [KeuanganApiController::class, 'belanja'])->name('belanja');
    Route::post('/pemasukan', [KeuanganApiController::class, 'pemasukan'])->name('pemasukan');
    Route::get('/saldo', [KeuanganApiController::class, 'saldo'])->name('saldo');
    Route::get('/dashboard-pimpinan', [KeuanganApiController::class, 'dashboardPimpinan'])->name('dashboard');
    Route::get('/slip-saya', [KeuanganApiController::class, 'slipSaya'])->name('slip');
    // WP-32: Setor Keliling di PWA (gate setor_tabungan di controller).
    Route::get('/setor-keliling/kamar', [KeuanganApiController::class, 'setorKelilingKamar'])->name('setor-keliling.kamar');
    Route::get('/setor-keliling/kamar/{kamar}/santri', [KeuanganApiController::class, 'setorKelilingSantri'])->name('setor-keliling.santri');
    Route::post('/setor-keliling/setoran', [KeuanganApiController::class, 'setorKelilingSetoran'])->name('setor-keliling.setoran');
    Route::get('/setor-keliling/sesi', [KeuanganApiController::class, 'setorKelilingSesi'])->name('setor-keliling.sesi');
    Route::post('/setor-keliling/ajukan', [KeuanganApiController::class, 'setorKelilingAjukan'])->name('setor-keliling.ajukan');
});
