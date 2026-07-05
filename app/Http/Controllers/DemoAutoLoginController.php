<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Auto-login demo untuk PWA/TWA showcase — melogin user demo bawaan lalu
 * mengarahkan ke PWA terkait tanpa kredensial.
 *
 * Dipakai oleh 5 aplikasi TWA demo (guru, wali, keuangan, keuangan-lapangan,
 * pimpinan) yang di-install di smartphone untuk peragaan.
 *
 * KUNCI GANDA (lihat config/demo.php):
 *   1. config('demo.autologin_enabled') harus true (env DEMO_AUTOLOGIN=true), DAN
 *   2. tenant aktif harus sama dengan config('demo.autologin_tenant') (default 'demo').
 * Selain itu → 404. Jadi aman walau kode ini ikut ter-deploy ke produksi.
 */
class DemoAutoLoginController extends Controller
{
    /**
     * peran => [email user demo, nama, role spatie, nama rute tujuan]
     *
     * Email 'wali@demo.pesantren.online' sengaja dipakai ulang dari
     * TenantDemoSeeder karena user itu sudah punya relasi access_wali ke santri
     * demo (dashboard wali butuh anak binaan). Peran lain dibuat on-the-fly.
     */
    private const PERAN = [
        'guru' => [
            'email' => 'guru.demo@demo.pesantren.online',
            'name' => 'Ustadz Demo',
            'role' => 'guru',
            'route' => 'tenant.portal-guru.beranda',
        ],
        'wali' => [
            'email' => 'wali@demo.pesantren.online',
            'name' => 'Bapak Ahmad (Wali)',
            'role' => 'wali',
            'route' => 'tenant.portal-wali.dashboard',
        ],
        'keuangan' => [
            'email' => 'keuangan.demo@demo.pesantren.online',
            'name' => 'Bendahara Demo',
            'role' => 'kepala_keuangan',
            'route' => 'tenant.kepala-keuangan.beranda',
        ],
        'keuangan-lapangan' => [
            'email' => 'kasir.demo@demo.pesantren.online',
            'name' => 'Kasir Demo',
            'role' => 'keuangan',
            'route' => 'tenant.keuangan.pwa.beranda',
        ],
        'pimpinan' => [
            'email' => 'pimpinan.demo@demo.pesantren.online',
            'name' => 'Pimpinan Demo',
            'role' => 'kepala',
            'route' => 'tenant.pimpinan.beranda',
        ],
        // App PWA demo peran (controller-dummy, gate auth). Role best-effort —
        // bila role tak ada di tenant, user tetap dibuat & login (gate hanya auth).
        'uks' => [
            'email' => 'uks.demo@demo.pesantren.online',
            'name' => 'Petugas UKS Demo',
            'role' => 'pengelola_uks',
            'route' => 'tenant.uks.beranda',
        ],
        'tu' => [
            'email' => 'tu.demo@demo.pesantren.online',
            'name' => 'Staf TU Demo',
            // FIX: role 'tata_usaha' tidak ada di RolesAndPermissionsSeeder
            // (nama sebenarnya 'tu') — sebelumnya bikin 403 di /tu.
            'role' => 'tu',
            'route' => 'tenant.tu.beranda',
        ],
        'humas' => [
            'email' => 'humas.demo@demo.pesantren.online',
            'name' => 'Staf Humas Demo',
            'role' => 'admin_pesantren',
            'route' => 'tenant.humas.beranda',
        ],
        'kepegawaian' => [
            'email' => 'kepegawaian.demo@demo.pesantren.online',
            'name' => 'Staf HRD Demo',
            'role' => 'kepala_keuangan',
            'route' => 'tenant.kepegawaian.beranda',
        ],
        'ppdb-panitia' => [
            'email' => 'ppdb.demo@demo.pesantren.online',
            'name' => 'Panitia PPDB Demo',
            // FIX: role 'tata_usaha' tidak ada — perlu 'panitia_ppdb' (gate
            // manage_ppdb) supaya tidak 403 di /ppdb-panitia.
            'role' => 'panitia_ppdb',
            'route' => 'tenant.ppdb-panitia.beranda',
        ],
        'pos-kantin' => [
            'email' => 'poskantin.demo@demo.pesantren.online',
            'name' => 'Kasir Kantin Demo',
            'role' => 'keuangan',
            'route' => 'tenant.pos-kantin.beranda',
        ],
        'keamanan' => [
            'email' => 'keamanan.demo@demo.pesantren.online',
            'name' => 'Petugas Keamanan Demo',
            // FIX: 'guru' tak punya view_kegiatan → 403 di /keamanan. Tak ada
            // role "keamanan" khusus (lihat KeamananController) — 'tu' punya
            // manage_kegiatan (mencakup view_kegiatan), gerbang paling dekat.
            'role' => 'tu',
            'route' => 'tenant.keamanan.beranda',
        ],
        'pengasuhan' => [
            'email' => 'pengasuhan.demo@demo.pesantren.online',
            'name' => 'Musyrif Demo',
            // FIX: 'guru' tak punya manage_asrama → 403 di /pengasuhan. Role
            // asli utk permission ini adalah pengelola_asrama.
            'role' => 'pengelola_asrama',
            'route' => 'tenant.pengasuhan.beranda',
        ],
        'pengabdian' => [
            'email' => 'pengabdian.demo@demo.pesantren.online',
            'name' => 'Santri Pengabdian Demo',
            'role' => 'santri',
            'route' => 'tenant.pengabdian.beranda',
        ],
        'konselor' => [
            'email' => 'konselor.demo@demo.pesantren.online',
            'name' => 'Konselor Demo',
            'role' => 'konselor',
            'route' => 'tenant.konselor.beranda',
        ],
        'sarpras' => [
            'email' => 'sarpras.demo@demo.pesantren.online',
            'name' => 'Petugas Sarpras Demo',
            'role' => 'pengelola_sarpras',
            'route' => 'tenant.sarpras.beranda',
        ],
        // F4 — akuntan (PWA baru, PR#430). Role `akuntan` sebelumnya tak
        // punya persona demo apa pun (0% PWA saat itu).
        'akuntan' => [
            'email' => 'akuntan.demo@demo.pesantren.online',
            'name' => 'Akuntan Demo',
            'role' => 'akuntan',
            'route' => 'tenant.akuntan.beranda',
        ],
        'santri' => [
            'email' => 'santri.demo@demo.pesantren.online',
            'name' => 'Santri Demo',
            'role' => 'santri',
            'route' => 'tenant.santri-app.beranda',
        ],
        // Admin & publik (1-aplikasi) — pakai 'url' (bukan named-route PWA).
        'admin' => [
            'email' => 'admin.demo@demo.pesantren.online',
            'name' => 'Admin Pesantren Demo',
            'role' => 'admin_pesantren',
            'url' => '/admin',
        ],
        'super-admin' => [
            'email' => 'superadmin.demo@demo.pesantren.online',
            'name' => 'Super Admin Demo',
            'role' => 'super_admin',
            'url' => '/admin',
        ],
        'jemaah' => [
            'email' => 'jemaah.demo@demo.pesantren.online',
            'name' => 'Jemaah Demo',
            'role' => 'jemaah',
            'url' => '/',
        ],
    ];

    /**
     * Peta persona demo (dibaca ulang oleh AuthApiController utk demo-login TOKEN
     * aplikasi native). Sumber tunggal agar persona demo konsisten lintas kanal.
     *
     * @return array<string, array{email:string, name:string, role:string, route?:string, url?:string}>
     */
    public static function personas(): array
    {
        return self::PERAN;
    }

    public function __invoke(string $peran): RedirectResponse
    {
        abort_unless((bool) config('demo.autologin_enabled'), 404);

        $tenantKey = optional(tenant())->getTenantKey();
        abort_unless($tenantKey === config('demo.autologin_tenant'), 404);

        abort_unless(isset(self::PERAN[$peran]), 404);
        $cfg = self::PERAN[$peran];

        $user = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => $cfg['email']],
            [
                'name' => $cfg['name'],
                'password' => Hash::make(Str::random(40)),
                'tenant_id' => $tenantKey,
            ],
        );

        if (Role::where('name', $cfg['role'])->exists() && ! $user->hasRole($cfg['role'])) {
            $user->syncRoles([$cfg['role']]);
        }

        Auth::login($user, remember: true);

        return isset($cfg['url'])
            ? redirect($cfg['url'])
            : redirect()->route($cfg['route']);
    }
}
