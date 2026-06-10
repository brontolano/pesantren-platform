<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rekonsiliasi harian lintas-tenant (invarian I-7): deteksi & alarmkan anomali
// status tagihan setiap dini hari. withoutOverlapping mencegah tumpang tindih
// bila eksekusi sebelumnya belum selesai (cukup untuk deploy 1 VPS).
// Pengingat daftar ulang (PPDB): calon lulus yang belum daftar ulang, tiap pagi.
Schedule::command('ppdb:ingatkan-daftar-ulang')
    ->dailyAt('08:00')
    ->withoutOverlapping();

Schedule::command('keuangan:rekonsiliasi-harian')
    ->dailyAt('01:30')
    ->withoutOverlapping();

// T-A002: alarm anomali biaya kredit AI per tenant (hari ini vs rata-rata
// 7 hari) — sore hari agar pemakaian harian sudah cukup terkumpul.
Schedule::command('ai:anomali')
    ->dailyAt('17:00')
    ->withoutOverlapping();

// T-A023: antrean Tanya Ustadz — auto-assign eskalasi menggantung + pengingat
// SLA per jam (loop semua tenant; pengingat hanya terkirim SEKALI per tenggat).
Schedule::command('ai:eskalasi-pengingat')
    ->hourly()
    ->withoutOverlapping();

// T-A023: laporan bulanan Tanya Ustadz untuk kyai/pimpinan — tanggal 1 pagi,
// merangkum bulan sebelumnya (idempoten per tenant+periode).
Schedule::command('ai:laporan-eskalasi')
    ->monthlyOn(1, '06:00')
    ->withoutOverlapping();
