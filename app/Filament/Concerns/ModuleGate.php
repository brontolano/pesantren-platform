<?php

namespace App\Filament\Concerns;

use App\Services\ModuleService;

/**
 * Gerbang visibilitas modul untuk Filament Resource.
 * Resource memanggil static::moduleVisible('key') di shouldRegisterNavigation()
 * dan canAccess() agar tersembunyi bila modul tidak tersedia untuk tenant aktif
 * (mis. modul "development" hanya tampil di tenant demo).
 */
trait ModuleGate
{
    protected static function moduleVisible(string $key): bool
    {
        // Bypass HANYA untuk pemilik PLATFORM (super_admin tanpa tenant) yang
        // mengelola lintas tenant. Super admin PESANTREN ikut gerbang modul
        // tenantnya agar navigasi ramping juga untuk akun pemilik pesantren.
        try {
            if (auth()->user()?->isPlatformSuperAdmin()) {
                return true;
            }
        } catch (\Throwable) {
            // tabel roles belum siap → lanjut ke gerbang normal
        }

        // Navigasi menampilkan modul yang DIPASANG (aktif) untuk tenant — bukan
        // hanya yang "siap". Disclosure bertahap dicapai lewat DEFAULT MINIMAL
        // (tenant baru hanya modul inti; modul muncul saat dipasang sendiri di
        // Pusat Modul), BUKAN dengan menyembunyikan modul aktif-belum-siap.
        //
        // PENTING: jangan gerbang nav pada "kesiapan" — resource untuk MENGISI
        // langkah setup (Lembaga, Periode, Halaqah, Pegawai, dll) digerbang oleh
        // modul yang sama; bila disembunyikan saat belum-siap, admin TAK BISA
        // membukanya untuk menyiapkan → deadlock. Status "siap/perlu disiapkan"
        // ditampilkan di Pusat Modul, bukan dijadikan gerbang akses.
        return app(ModuleService::class)
            ->isVisibleForTenant($key, tenancy()->initialized ? tenant() : null);
    }
}
