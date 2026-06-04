<?php

use Illuminate\Support\Facades\Route;
use Modules\Kegiatan\Http\Controllers\KegiatanController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('kegiatans', KegiatanController::class)->names('kegiatan');
});
