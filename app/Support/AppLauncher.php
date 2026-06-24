<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Resolver pusat "satu pintu". Menentukan ke mana seorang user diarahkan dari
 * launcher (route `home` pada domain bertipe `sistem`) dan aplikasi/panel apa
 * yang boleh ia buka.
 *
 * Berbasis PERMISSION (bukan nama role) supaya tahan multi-role, alias role,
 * dan rename. Sumber kebenaran tunggal yang dipakai oleh LauncherController,
 * view hub, dan (opsional) DemoAutoLoginController.
 *
 * Catatan multi-role: definisi "staf" (User::isTenantStaff) SUDAH mengecualikan
 * pemilik role `wali`/`peserta` — konsisten dengan canAccessPanel. Maka akun
 * keluarga/eksternal selalu diarahkan ke portalnya sendiri lebih dulu; ini
 * sengaja agar tidak ada akun wali/peserta yang nyasar ke hub/panel staf.
 */
final class AppLauncher
{
    /**
     * Daftar aplikasi PWA staf, diurutkan PRIORITAS (atas = paling utama).
     * Tile pertama yang izin permission-nya dimiliki user = beranda utamanya.
     *
     * @var array<int, array{permission:string, route:string, path:string, label:string, icon:string}>
     */
    private const STAFF_APPS = [
        [
            'permission' => 'akses_pimpinan',
            'route' => 'tenant.pimpinan.beranda',
            'path' => '/pimpinan',
            'label' => 'Dasbor Pimpinan',
            'icon' => '<path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>',
        ],
        [
            // close_period = pemimpin keuangan (kepala_keuangan/bendahara_pusat);
            // manage_keuangan terlalu luas (admin_pesantren pun punya).
            'permission' => 'close_period',
            'route' => 'tenant.kepala-keuangan.beranda',
            'path' => '/keuangan',
            'label' => 'Aplikasi Keuangan',
            'icon' => '<path d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/>',
        ],
        [
            'permission' => 'operate_pos',
            'route' => 'tenant.keuangan.pwa.beranda',
            'path' => '/admin/keuangan/pwa',
            'label' => 'Kasir Lapangan',
            'icon' => '<path d="M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V13.5Zm0 2.25h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V18Zm2.498-6.75h.007v.008h-.007v-.008Zm0 2.25h.007v.008h-.007V13.5Zm0 2.25h.007v.008h-.007v-.008Zm0 2.25h.007v.008h-.007V18Zm2.504-6.75h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V13.5Zm2.498-4.5h.008v.008h-.008V9Zm0 2.25h.008v.008h-.008v-.008ZM8.25 6h7.5v2.25h-7.5V6ZM12 2.25c-1.892 0-3.758.11-5.593.322C5.307 2.7 4.5 3.65 4.5 4.757V19.5a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V4.757c0-1.108-.806-2.057-1.907-2.185A48.507 48.507 0 0 0 12 2.25Z"/>',
        ],
        [
            'permission' => 'akses_pwa_guru',
            'route' => 'tenant.portal-guru.beranda',
            'path' => '/guru',
            'label' => 'Aplikasi Guru',
            'icon' => '<path d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5"/>',
        ],
    ];

    /**
     * Tujuan utama user saat membuka launcher.
     *
     * @return array{kind:'hub'|'redirect', url?:string}
     */
    public static function primaryDestination(User $user): array
    {
        // Akun keluarga/eksternal = satu tujuan, lewati hub staf.
        // PENTING: cek BERBASIS ROLE, bukan can('view_own_children'). super_admin
        // mendapat Gate::before "boleh segalanya" (AppServiceProvider) → can(apa pun)
        // SELALU true untuknya, sehingga klausa can() dulu menyeret super_admin/admin
        // ke /wali (lalu 403 karena EnsureUserIsWali butuh role wali). view_own_children
        // pun HANYA berasal dari role wali (RolesAndPermissionsSeeder), jadi hasRole()
        // sudah cukup & benar.
        if ($user->hasRole('wali')) {
            return ['kind' => 'redirect', 'url' => self::routeOr('tenant.portal-wali.dashboard', '/wali')];
        }
        if ($user->hasRole('peserta')) {
            return ['kind' => 'redirect', 'url' => self::routeOr('spmb.dashboard', '/spmb')];
        }
        // Jemaah (peran publik, 1-aplikasi) → Beranda publik '/'. Berbasis ROLE
        // (bukan can()) demi alasan yang sama: super_admin di-bypass Gate::before.
        // Diletakkan setelah wali/peserta agar akun dual-peran tetap diutamakan
        // ke portal keluarganya, lalu jemaah, sebelum logika staf/hub.
        if ($user->hasRole('jemaah')) {
            return ['kind' => 'redirect', 'url' => url('/')];
        }
        // Santri MURNI (tanpa kapasitas staf) → aplikasi santri. Dicek sebelum
        // "hub" karena canAccessPanel tidak mengecualikan role santri.
        if ($user->hasRole('santri') && ! self::hasStaffCapability($user)) {
            return ['kind' => 'redirect', 'url' => self::routeOr('tenant.santri-app.beranda', '/santri-app')];
        }

        $adminUrl = self::adminPanelUrl($user);

        // super_admin = admin penuh tenant (dibuat TenantProvisioner) → langsung
        // /admin. TIDAK bisa memakai pwaHome() sebagai pembeda untuknya karena can()
        // di-bypass Gate::before → pwaHome palsu non-null (seolah punya app pimpinan).
        if ($adminUrl !== null && $user->hasRole('super_admin')) {
            return ['kind' => 'redirect', 'url' => $adminUrl];
        }

        // Staf admin tanpa aplikasi PWA khusus (mis. admin_pesantren) → langsung
        // ke panel administrasi /admin. Hub-nya hanya akan berisi satu tile panel,
        // jadi mampir hub cuma menambah satu klik. Staf yang PUNYA aplikasi PWA
        // (guru/keuangan/pimpinan/kasir) tetap melihat hub agar bisa memilih
        // aplikasi vs panel.
        if ($adminUrl !== null && self::pwaHome($user) === null) {
            return ['kind' => 'redirect', 'url' => $adminUrl];
        }

        // Staf dengan aplikasi PWA (dan fallback authenticated-tanpa-app) → hub.
        return ['kind' => 'hub'];
    }

    /** Kapasitas operasional staf — memilah santri murni dari santri-yang-juga-staf. */
    private static function hasStaffCapability(User $user): bool
    {
        return $user->canAny([
            'akses_pimpinan', 'akses_pwa_guru', 'close_period', 'operate_pos',
            'manage_keuangan', 'manage_santri', 'manage_users', 'manage_akademik',
            'manage_modules', 'manage_roles', 'kelola_approval',
        ]);
    }

    /**
     * Beranda PWA utama untuk staf (tile "Aplikasi" di hub), atau null bila staf
     * tak punya akses PWA mana pun (hub menampilkan empty-state ramah).
     *
     * @return array{label:string, url:string, icon:string}|null
     */
    public static function pwaHome(User $user): ?array
    {
        foreach (self::STAFF_APPS as $app) {
            if ($user->can($app['permission'])) {
                return [
                    'label' => $app['label'],
                    'url' => self::routeOr($app['route'], $app['path']),
                    'icon' => $app['icon'],
                ];
            }
        }

        return null;
    }

    /** URL panel Administrasi (Filament /admin) bila user staf, else null. */
    public static function adminPanelUrl(User $user): ?string
    {
        return $user->isTenantStaff() ? url('/admin') : null;
    }

    /**
     * URL situs web publik tenant untuk tile "Website" — dinamis, tanpa hardcode.
     * Prioritas: Setting `branding_website_url`, lalu domain bertipe `website`
     * milik tenant (tabel domains di DB central). Null bila tak ada → tile
     * disembunyikan.
     */
    public static function websiteUrl(): ?string
    {
        try {
            $url = Setting::getTenantStringValue('branding_website_url', null);
            if (is_string($url) && trim($url) !== '') {
                return self::normalizeUrl(trim($url));
            }
        } catch (\Throwable) {
            // fallthrough
        }

        try {
            $tenant = tenant();
            if ($tenant !== null) {
                $domain = $tenant->domains()->where('type', 'website')->value('domain');
                if (is_string($domain) && trim($domain) !== '') {
                    return self::normalizeUrl(trim($domain));
                }
            }
        } catch (\Throwable) {
            // fallthrough
        }

        return null;
    }

    private static function routeOr(string $name, string $fallback): string
    {
        return Route::has($name) ? route($name) : url($fallback);
    }

    private static function normalizeUrl(string $value): string
    {
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return 'https://' . ltrim($value, '/');
    }
}
