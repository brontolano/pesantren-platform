<?php

use Illuminate\Support\Facades\Route;
use Modules\Kegiatan\Http\Controllers\KegiatanController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('kegiatans', KegiatanController::class)->names('kegiatan');
});
