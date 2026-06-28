<?php

use App\Http\Middleware\AuthenticateTenantApiKey;
use App\Http\Middleware\CheckModuleActive;
use App\Http\Middleware\EnsureUserIsJemaah;
use App\Http\Middleware\EnsureUserIsWali;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InitializeTenancyForWebRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/tenant.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(
            prepend: [
                // Tenancy init untuk request grup web (Livewire dll) di domain tenant
                InitializeTenancyForWebRequests::class,
            ],
            append: [
                HandleInertiaRequests::class,
                // Header keamanan global aman (nosniff/HSTS/Referrer-Policy).
                SecurityHeaders::class,
            ],
        );
        $middleware->api(append: [
            SecurityHeaders::class,
        ]);
        $middleware->alias([
            'module' => CheckModuleActive::class,
            'wali' => EnsureUserIsWali::class,
            'jemaah' => EnsureUserIsJemaah::class,
            'api.key' => AuthenticateTenantApiKey::class,
        ]);

        // API publik (lintas-origin dari website tenant) — stateless, dilindungi CORS.
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Host non-tenant & non-central (IP mentah, subdomain scanner/bot seperti
        // ww25.*, store.*) tidak perlu menghasilkan HTTP 500 yang ter-log sebagai
        // ERROR. Jangan laporkan, dan render 404 ringan & netral.
        // (www.* sudah ditangani via redirect 301 di InitializeTenancyForWebRequests.)
        $exceptions->dontReport(
            TenantCouldNotBeIdentifiedOnDomainException::class,
        );
        $exceptions->render(function (
            TenantCouldNotBeIdentifiedOnDomainException $e,
            Request $request,
        ) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Not Found.'], 404);
            }

            return response(
                '<!doctype html><meta charset="utf-8">'
                .'<meta name="viewport" content="width=device-width,initial-scale=1">'
                .'<title>404 — Tidak Ditemukan</title>'
                .'<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;'
                .'max-width:32rem;margin:18vh auto;padding:0 1.5rem;text-align:center;color:#374151">'
                .'<h1 style="font-size:3.5rem;margin:0;color:#14532d">404</h1>'
                .'<p style="font-size:1.05rem">Halaman atau alamat ini tidak ditemukan.</p>'
                .'<p><a href="https://pesantren.online" style="color:#c2410c;text-decoration:none">'
                .'Kembali ke pesantren.online &rarr;</a></p></div>',
                404
            );
        });

        // 403 ANGGUN di panel tenant: bila staf login menjangkau halaman di luar
        // kewenangannya (mis. ketik URL /admin/.../edit langsung, bookmark/riwayat
        // basi, atau tautan dalam dari peran lain), JANGAN tampilkan "403 Forbidden"
        // telanjang yang membingungkan. Alihkan kembali ke Dasbor + notifikasi ramah.
        // Backend tetap MENOLAK (keamanan utuh) — hanya UX-nya yang dibuat lembut.
        // 403 di luar panel / tamu / JSON / halaman home (cegah loop) → render
        // halaman branded resources/views/errors/403.blade.php (fallback).
        $exceptions->render(function (
            \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e,
            Request $request,
        ) {
            if ($e->getStatusCode() !== 403) {
                return null;
            }
            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }
            if (auth()->check() && $request->is('admin/*') && ! $request->is('admin')) {
                \Filament\Notifications\Notification::make()
                    ->title('Akses ditolak')
                    ->body('Halaman tersebut di luar kewenangan akun Anda. Anda dialihkan kembali ke Dasbor.')
                    ->danger()
                    ->persistent()
                    ->send();

                // PENTING: pakai RedirectResponse Laravel EKSPLISIT — JANGAN helper
                // redirect(). Mount halaman Filament berjalan dalam konteks Livewire
                // yang me-rebind 'redirect' ke Livewire\...\Redirector (tanpa ->headers);
                // saat response melewati middleware CSRF (PreventRequestForgery) →
                // "Undefined property ...Redirector::$headers" → 500. RedirectResponse
                // murni Symfony punya ->headers, jadi aman di Livewire & non-Livewire.
                return new \Illuminate\Http\RedirectResponse(url('admin'), 302);
            }

            return null;
        });
    })->create();
