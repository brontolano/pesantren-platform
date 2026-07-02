<?php

namespace App\Providers;

use App\Services\AuditLogger;
use App\Support\PublicApiThrottle;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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

        // SUPER ADMIN = lolos SEMUA permission check (pola resmi spatie).
        // Future-proof: permission baru otomatis dimiliki tanpa re-seed.
        // Return null (bukan false) agar user lain tetap dievaluasi normal.
        Gate::before(function ($user, string $ability) {
            try {
                return method_exists($user, 'hasRole') && $user->hasRole('super_admin') ? true : null;
            } catch (\Throwable) {
                return null; // tabel roles belum ada (mis. saat migrasi awal)
            }
        });

        /*
         * Fix: Livewire temporary file upload HARUS pakai disk yang TIDAK masuk
         * tenancy.filesystem.disks. Disk 'local' ada di daftar tenancy → root-nya
         * berubah per-tenant oleh FilesystemTenancyBootstrapper. Upload ke
         * /livewire/upload-file berjalan TANPA tenant context (file disimpan di
         * path normal), tapi callback Filament berjalan WITH tenant context
         * (mencari file di path tenant) → mismatch → loop "Menunggu ukuran berkas".
         * Disk 'livewire-tmp' di config/filesystems.php sengaja TIDAK ada di
         * tenancy.filesystem.disks → path-nya selalu statis → upload langsung bekerja.
         */
        config(['livewire.temporary_file_upload.disk' => 'livewire-tmp']);

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

        // Limiter ketat khusus submit PPDB publik (anti-spam form pendaftaran).
        // Per tenant + IP; default 5/menit (override config services.public_api.ppdb_submit_per_minute).
        RateLimiter::for('ppdb-submit', function (Request $request) {
            $tenantId = tenant()?->getTenantKey() ?? 'central';
            $perMinute = (int) config('services.public_api.ppdb_submit_per_minute', 5);

            return Limit::perMinute(max(1, $perMinute))
                ->by('ppdb-submit:'.$tenantId.':'.$request->ip());
        });

        // 1-aplikasi (Fase 2c) — kirim komentar artikel. Kunci di-prefix tenant +
        // user agar id numerik yang sama lintas tenant (DB-per-tenant) TIDAK saling
        // menggerus jatah. Pola sama dgn throttleKey JemaahAuthController.
        RateLimiter::for('komentar', function (Request $request) {
            $tenantId = tenant()?->getTenantKey() ?? 'central';
            $by = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(10)->by('komentar:'.$tenantId.':'.$by);
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
