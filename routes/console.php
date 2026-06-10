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
