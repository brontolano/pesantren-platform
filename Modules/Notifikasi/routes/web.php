<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('tenant/notifikasi')->name('tenant.notifikasi.')->group(function (): void {
    Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');
});
