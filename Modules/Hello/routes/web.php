<?php

use Illuminate\Support\Facades\Route;
use Modules\Hello\Http\Controllers\HelloController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('hellos', HelloController::class)->names('hello');
});
