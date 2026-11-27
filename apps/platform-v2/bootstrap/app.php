<?php

use App\Http\Middleware\ResolvePanelSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(ResolvePanelSession::class);
        $middleware->append(App\Http\Middleware\ResolveTenantContext::class);
        $middleware->redirectGuestsTo(function (Request $request): string {
            return $request->is('pondok', 'pondok/*') ? route('tenant.login') : route('platform.login');
        });
        $middleware->alias([
            'platform.operator' => App\Http\Middleware\EnsurePlatformOperator::class,
            'tenant.session' => App\Http\Middleware\EnsureTenantSessionMatches::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Laravel's default safe exception handling is retained.
    })
    ->create();