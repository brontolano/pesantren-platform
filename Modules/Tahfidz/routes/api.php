<?php

use Illuminate\Support\Facades\Route;
use Modules\Tahfidz\Http\Controllers\TahfidzController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('tahfidzs', TahfidzController::class)->names('tahfidz');
});
