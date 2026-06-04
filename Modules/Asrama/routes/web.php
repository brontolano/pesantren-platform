<?php

use Illuminate\Support\Facades\Route;
use Modules\Asrama\Http\Controllers\AsramaController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('asramas', AsramaController::class)->names('asrama');
});
