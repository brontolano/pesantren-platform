<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing', [
        'tenants' => Tenant::all(),
    ]);
});
