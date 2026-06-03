<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('notifikasi')->name('notifikasi.')->group(function (): void {
    Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');
});
