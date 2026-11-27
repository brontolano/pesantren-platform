<?php

use App\Http\Controllers\Platform\PlatformLoginController;
use App\Http\Controllers\Platform\PlatformConsoleController;
use App\Http\Controllers\Tenant\TenantConsoleController;
use App\Http\Controllers\Tenant\TenantInvitationController;
use App\Http\Controllers\Tenant\TenantLoginController;
use App\Http\Controllers\Tenant\TenantModuleController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/platform');

Route::prefix('platform')->name('platform.')->group(function (): void {
    Route::get('/login', [PlatformLoginController::class, 'create'])->middleware('guest:platform')->name('login');
    Route::post('/login', [PlatformLoginController::class, 'store'])->middleware('guest:platform')->name('login.store');
    Route::post('/logout', [PlatformLoginController::class, 'destroy'])->middleware('auth:platform')->name('logout');
    Route::middleware(['auth:platform', 'platform.operator'])->group(function (): void {
        Route::get('/', [PlatformConsoleController::class, 'dashboard'])->name('dashboard');
        Route::get('/organisasi', [PlatformConsoleController::class, 'organizations'])->name('organizations.index');
        Route::post('/organisasi', [PlatformConsoleController::class, 'storeDraft'])->name('organizations.store');
        Route::post('/organisasi/{organization}/setup', [PlatformConsoleController::class, 'setup'])->name('organizations.setup');
        Route::post('/organisasi/{organization}/status-layanan', [PlatformConsoleController::class, 'updateServiceState'])->name('organizations.service-state');
        Route::post('/organisasi/{organization}/pemeriksaan', [PlatformConsoleController::class, 'check'])->name('organizations.check');
        Route::get('/modul', [PlatformConsoleController::class, 'modules'])->name('modules');
        Route::get('/log-aktivitas', [PlatformConsoleController::class, 'activity'])->name('activity');
    });
});

Route::prefix('pondok')->name('tenant.')->group(function (): void {
    Route::get('/login', [TenantLoginController::class, 'create'])->middleware('guest:tenant')->name('login');
    Route::post('/login', [TenantLoginController::class, 'store'])->middleware('guest:tenant')->name('login.store');
    Route::get('/invitation/{token}', [TenantInvitationController::class, 'create'])->name('invitation.create');
    Route::post('/invitation/{token}', [TenantInvitationController::class, 'store'])->name('invitation.store');
    Route::post('/logout', [TenantLoginController::class, 'destroy'])->middleware('auth:tenant')->name('logout');
    Route::middleware(['auth:tenant', 'tenant.session'])->get('/beranda', [TenantConsoleController::class, 'dashboard'])->name('dashboard');
    Route::middleware(['auth:tenant', 'tenant.session'])->get('/modul/{moduleKey}', [TenantModuleController::class, 'show'])->name('modules.show');
});
