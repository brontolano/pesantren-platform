<?php

use App\Http\Controllers\Api\ThemeApiController;
use App\Http\Controllers\Api\WilayahController;
use Illuminate\Support\Facades\Route;

Route::prefix('wilayah')->group(function () {
    Route::get('provinces', [WilayahController::class, 'provinces']);
    Route::get('cities/{provinceId}', [WilayahController::class, 'cities']);
    Route::get('districts/{cityId}', [WilayahController::class, 'districts']);
    Route::get('villages/{districtId}', [WilayahController::class, 'villages']);
});

// API Tema Panel Admin — authenticated tenant user dengan manage_tenant_settings
Route::prefix('tenant')->middleware('auth:sanctum')->group(function () {
    Route::get('/themes', [ThemeApiController::class, 'listThemes']);
    Route::get('/themes/{themeKey}', [ThemeApiController::class, 'getTheme']);
    Route::put('/theme-settings', [ThemeApiController::class, 'updateThemeSettings']);
});
