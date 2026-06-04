<?php

use Illuminate\Support\Facades\Route;
use Modules\Tahfidz\Http\Controllers\TahfidzController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('tahfidzs', TahfidzController::class)->names('tahfidz');
});
