<?php

use Illuminate\Support\Facades\Route;
use Modules\Hello\Http\Controllers\HelloController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('hellos', HelloController::class)->names('hello');
});
