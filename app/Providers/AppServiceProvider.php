<?php

namespace App\Providers;

use App\Services\AuditLogger;
use App\Support\PublicApiThrottle;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        // Named limiter API publik headless (per API-key/tenant+IP). Pakai pada
        // route dgn middleware 'throttle:public-api'.
        RateLimiter::for('public-api', function (Request $request) {
            $key = PublicApiThrottle::resolveKey(
                tenant()?->getTenantKey(),
                $request->attributes->get('api_key_prefix'),
                (string) $request->ip(),
            );

            return Limit::perMinute(PublicApiThrottle::perMinute())->by($key);
        });

        Event::listen(Login::class, function (Login $event): void {
            app(AuditLogger::class)->log(
                action: 'auth.login',
                user: $event->user,
                metadata: [
                    'guard' => $event->guard,
                    'remember' => $event->remember,
                ],
            );
        });

        Event::listen(Logout::class, function (Logout $event): void {
            app(AuditLogger::class)->log(
                action: 'auth.logout',
                user: $event->user,
                metadata: [
                    'guard' => $event->guard,
                ],
            );
        });

        Event::listen(PasswordReset::class, function (PasswordReset $event): void {
            app(AuditLogger::class)->log(
                action: 'auth.password_reset',
                user: $event->user,
            );
        });
    }
}
