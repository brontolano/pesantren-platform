<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing', [
        'tenants' => Tenant::all(),
    ]);
});

// Halaman legal — kebijakan privasi & syarat layanan
Route::get('/kebijakan-privasi', fn () => view('legal.privacy-policy'))->name('legal.privacy');
Route::get('/syarat-layanan', fn () => view('legal.terms-of-service'))->name('legal.terms');
