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
Schedule::command('keuangan:rekonsiliasi-harian')
    ->dailyAt('01:30')
    ->withoutOverlapping();
