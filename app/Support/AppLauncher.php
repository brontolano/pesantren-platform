<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
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
            // operator_divisi = "input belanja unitnya" (RolesAndPermissionsSeeder).
            // Shell KeuanganPwaController SUDAH adaptif per-izin (nav
            // menampilkan tab "Belanja" hanya jika input_belanja) — dipakai
            // apa adanya, bukan halaman baru. Sebelumnya operator_divisi
            // tak match tile mana pun → jatuh ke /staf generik walau app
            // yang tepat (dan endpoint tulisnya) sudah ada & teruji.
            'permission' => 'input_belanja',
            'route' => 'tenant.keuangan.pwa.beranda',
            'path' => '/admin/keuangan/pwa',
            'label' => 'Keuangan Lapangan',
            'icon' => '<path d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/>',
        ],
        [
            // dewan_pembina = pengawasan (view-only): view_laporan_pimpinan
            // SAJA, tanpa input_belanja/close_period apa pun (RolesAndPermissionsSeeder
            // — peran ini sengaja tak bisa mencatat transaksi). Shell yang sama
            // (KeuanganPwaController) menampilkan tab "Dasbor" saja untuknya.
            'permission' => 'view_laporan_pimpinan',
            'route' => 'tenant.keuangan.pwa.beranda',
            'path' => '/admin/keuangan/pwa',
            'label' => 'Dasbor Keuangan',
            'icon' => '<path d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/>',
        ],
        [
            'permission' => 'akses_pwa_guru',
            'route' => 'tenant.portal-guru.beranda',
            'path' => '/guru',
            'label' => 'Aplikasi Guru',
            'icon' => '<path d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5"/>',
        ],
        [
            // F4 — petugas UKS/kesehatan. Permission `view_kesehatan` sudah
            // dimiliki role pengelola_uks (RolesAndPermissionsSeeder).
            'permission' => 'view_kesehatan',
            'route' => 'tenant.uks.beranda',
            'path' => '/uks',
            'label' => 'Kesehatan UKS',
            'icon' => '<path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/>',
        ],
        [
            // F4 — staf Tata Usaha. Permission `manage_santri` sama dgn gerbang
            // SuratResource::canAccess() di panel admin — dipakai apa adanya,
            // bukan permission baru. Dibagi role lain (mis. admin_pesantren) →
            // itu tetap muncul di hub-nya, konsisten dgn tile UKS/keuangan lain.
            'permission' => 'manage_santri',
            'route' => 'tenant.tu.beranda',
            'path' => '/tu',
            'label' => 'Tata Usaha',
            'icon' => '<path d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/>',
        ],
        [
            // F4 — panitia PPDB. Permission `manage_ppdb` sama dgn gerbang
            // CalonSantriResource::canAccess() di panel admin.
            'permission' => 'manage_ppdb',
            'route' => 'tenant.ppdb-panitia.beranda',
            'path' => '/ppdb-panitia',
            'label' => 'PPDB Panitia',
            'icon' => '<path d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/>',
        ],
        [
            // F4 — musyrif/pengelola asrama. Permission `manage_asrama` sama dgn
            // gerbang KamarAsramaResource/PenempatanAsramaResource::canCreate()
            // di panel admin (view_asrama dipakai utk canAccess baca-saja;
            // manage_asrama dipilih di sini krn musyrif JUGA mencatat presensi/
            // disiplin, bukan cuma melihat) — role pengelola_asrama sudah
            // memilikinya (RolesAndPermissionsSeeder).
            'permission' => 'manage_asrama',
            'route' => 'tenant.pengasuhan.beranda',
            'path' => '/pengasuhan',
            'label' => 'Pengasuhan/Asrama',
            'icon' => '<path d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>',
        ],
        [
            // F4 — petugas keamanan/satpam. TIDAK ada permission "keamanan"
            // khusus di RolesAndPermissionsSeeder — `view_kegiatan` dipakai
            // apa adanya, permission yang SAMA dgn gerbang LogGerbangPanel &
            // KunjunganWaliResource::canAccess() di panel admin (data gerbang/
            // tamu ada di modul Kegiatan). Dibagi role lain (admin_pesantren/
            // tu/kepala) → tetap muncul di hub-nya masing2, konsisten dgn
            // tile UKS/TU/PPDB lain.
            'permission' => 'view_kegiatan',
            'route' => 'tenant.keamanan.beranda',
            'path' => '/keamanan',
            'label' => 'Pos Keamanan',
            'icon' => '<path d="M9 12.75 11.25 15 15 9.75M21 12c0 4.556-3.86 8.25-8.625 8.25S3.75 16.556 3.75 12 7.61 3.75 12.375 3.75 21 7.444 21 12Z"/>',
        ],
        [
            // F4 — petugas payroll/kepegawaian. Permission `run_payroll` sama dgn
            // gerbang EmployeeResource/CutiResource/PresensiStafResource/
            // SlipGajiResource::canAccess() di panel admin (Modules/Payroll).
            // Sebelumnya `petugas_payroll` TIDAK match tile mana pun di daftar
            // ini (tidak ada dead-code lama yang tergantikan).
            'permission' => 'run_payroll',
            'route' => 'tenant.kepegawaian.beranda',
            'path' => '/kepegawaian',
            'label' => 'Kepegawaian',
            'icon' => '<path d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>',
        ],
        [
            // F4 — konselor. Permission `view_konseling` sama dgn gerbang
            // SesiKonselingResource::canAccess() di panel admin. Role
            // `konselor` sebelumnya tak punya PWA sama sekali (bukan
            // dummy-ke-nyata, genuinely fitur baru).
            'permission' => 'view_konseling',
            'route' => 'tenant.konselor.beranda',
            'path' => '/konselor',
            'label' => 'Konselor',
            'icon' => '<path d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/>',
        ],
        [
            // F4 — pengelola sarpras. Permission `manage_sarpras` sama dgn
            // gerbang AreaPiketResource/LaporanKerusakanResource di panel
            // admin. Role `pengelola_sarpras` sebelumnya tak punya PWA sama
            // sekali (bukan dummy-ke-nyata, genuinely fitur baru).
            'permission' => 'manage_sarpras',
            'route' => 'tenant.sarpras.beranda',
            'path' => '/sarpras',
            'label' => 'Sarpras',
            'icon' => '<path d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z"/>',
        ],
        [
            // F4 — akuntan. Permission `view_ledger` sama dgn gerbang
            // JurnalResource/AkunResource::canAccess() di panel admin. Role
            // `akuntan` sebelumnya tak punya PWA sama sekali (0%) — kepala_keuangan/
            // bendahara_pusat/operator_divisi tetap match tile close_period/
            // input_belanja LEBIH DULU (di atas), jadi entry ini murni utk akuntan.
            'permission' => 'view_ledger',
            'route' => 'tenant.akuntan.beranda',
            'path' => '/akuntan',
            'label' => 'Akuntan',
            'icon' => '<path d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21"/>',
        ],
        // Catatan: Baitul Maal (/baitul-maal, permission view_donasi) TIDAK
        // didaftarkan di STAFF_APPS — pemegang view_donasi (keuangan/
        // bendahara_pusat/kepala_keuangan) SELALU match tile operate_pos/
        // close_period lebih dulu (di atas), jadi entry kedua akan jadi dead
        // code (sama seperti keputusan Humas di bawah). Tetap dapat diakses
        // langsung via URL bagi staf yang memegang view_donasi.
        // Catatan: Humas TIDAK didaftarkan di STAFF_APPS. Tak ada role/
        // permission "humas" khusus di RolesAndPermissionsSeeder — gerbangnya
        // memakai `manage_santri`, permission yang sama dgn tile Tata Usaha di
        // atas. STAFF_APPS bersifat first-match-only (lihat pwaHome()), jadi
        // menambah entry kedua dgn permission yang sama akan selalu kalah
        // dari tile TU dan jadi dead code murni — sama seperti keputusan utk
        // POS Kantin (PR#413). Halaman /humas tetap dapat diakses langsung
        // via URL bagi staf yang memegang `manage_santri`.
    ];

    /**
     * Tujuan utama user saat membuka launcher.
     *
     * @return array{kind:'hub'|'redirect', url?:string}
     */
    public static function primaryDestination(User $user, bool $isMobile = false): array
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

        // Bukan staf berkapasitas panel (dan bukan peran keluarga di atas) → hub
        // fallback ramah (jarang terjadi).
        if ($adminUrl === null) {
            return ['kind' => 'hub'];
        }

        // ========================================================================
        // ATURAN DEVICE (ditegaskan owner 2026-07-06): "desktop = panel admin,
        // HP = TWA/PWA". Tujuan staf ditentukan PERANGKAT, bukan peran:
        //   - Desktop  → panel Filament `/admin` (layar besar, fitur penuh).
        //   - HP/mobile → PWA Nur: aplikasi peran KHUSUS bila ada (guru/keuangan/
        //     uks/konselor/…), selain itu shell staf mobile `/staf`.
        // Ini menghapus "hub pemilih" (dulu ambigu) — device yang memutuskan.
        // ========================================================================
        if (! $isMobile) {
            return ['kind' => 'redirect', 'url' => $adminUrl];
        }

        // super_admin: pwaHome() TAK ANDAL (Gate::before → can() selalu true →
        // tile pertama palsu). Beri shell staf mobile penuh `/staf` (punya semua menu).
        if (! $user->hasRole('super_admin')) {
            $pwa = self::pwaHome($user);
            if ($pwa !== null) {
                return ['kind' => 'redirect', 'url' => $pwa['url']];
            }
        }

        return ['kind' => 'redirect', 'url' => self::routeOr('tenant.staf-shell', '/staf')];
    }

    /** Kapasitas operasional staf — memilah santri murni dari santri-yang-juga-staf. */
    private static function hasStaffCapability(User $user): bool
    {
        return $user->canAny([
            'akses_pimpinan', 'akses_pwa_guru', 'close_period', 'operate_pos',
            'manage_keuangan', 'manage_santri', 'manage_users', 'manage_akademik',
            'manage_modules', 'manage_roles', 'kelola_approval',
            'view_kesehatan', 'manage_kesehatan', 'manage_ppdb', 'manage_asrama', 'view_kegiatan', 'run_payroll',
            'view_konseling', 'manage_sarpras', 'input_belanja', 'view_laporan_pimpinan', 'view_ledger',
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

    /**
     * Deteksi HP/mobile untuk aturan device (desktop→panel, HP→PWA). PWA/TWA
     * Android standalone (Sec-Fetch-Dest document + Referer android) DAN WebView
     * app (userAgent 'PesantrenApp') selalu dianggap mobile.
     */
    public static function isMobileRequest(Request $request): bool
    {
        // Sinyal device NYATA yang ditangkap saat login (viewport/pointer klien)
        // disimpan di sesi — DIUTAMAKAN agar keputusan desktop/HP konsisten di
        // seluruh request sesi, tak bergantung UA yang bisa keliru.
        if ($request->hasSession() && $request->session()->has('device_mobile')) {
            return (bool) $request->session()->get('device_mobile');
        }

        if ($request->header('Sec-Fetch-Dest') === 'document'
            && str_contains((string) $request->header('Referer', ''), 'android')) {
            return true;
        }

        $ua = strtolower((string) $request->header('User-Agent', ''));

        return (bool) preg_match(
            '/mobile|android|iphone|ipad|ipod|blackberry|iemobile|opera mini|webos|windows phone|pesantrenapp/i',
            $ua
        );
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

        return 'https://'.ltrim($value, '/');
    }
}
