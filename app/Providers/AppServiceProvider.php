<?php

namespace App\Providers;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
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
