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
    ];

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

        return redirect()->route($cfg['route']);
    }
}
