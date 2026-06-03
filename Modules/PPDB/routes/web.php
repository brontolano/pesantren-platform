<?php

use Illuminate\Support\Facades\Route;
use Modules\PPDB\Http\Controllers\PublicPpdbController;
use Modules\PPDB\Http\Controllers\PPDBController;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/ppdb', [PublicPpdbController::class, 'create'])
        ->name('tenant.ppdb.public.create');
    Route::post('/ppdb', [PublicPpdbController::class, 'store'])
        ->name('tenant.ppdb.public.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('ppdbs', PPDBController::class)->names('ppdb');
});
