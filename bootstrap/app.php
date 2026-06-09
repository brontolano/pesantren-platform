<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
                \App\Http\Middleware\InitializeTenancyForWebRequests::class,
            ],
            append: [
                \App\Http\Middleware\HandleInertiaRequests::class,
            ],
        );
        $middleware->alias([
            'module' => \App\Http\Middleware\CheckModuleActive::class,
            'wali' => \App\Http\Middleware\EnsureUserIsWali::class,
            'api.key' => \App\Http\Middleware\AuthenticateTenantApiKey::class,
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
            \Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException::class,
        );
        $exceptions->render(function (
            \Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException $e,
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
    })->create();
