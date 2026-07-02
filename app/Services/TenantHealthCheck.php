<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\TenantStorage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

/**
 * Pemeriksaan kesehatan tenant pasca-instalasi (CENTRAL).
 *
 * Dipakai wizard Instalasi Tenant untuk memverifikasi hasil provisioning
 * sebelum kredensial diserahkan — menutup celah "tenant setengah jadi"
 * (seeder gagal di tengah tanpa ada yang tahu, gejala baru muncul saat
 * dipakai: assignRole gagal, FK violation di akuntansi, panel 500).
 *
 * Semua cek read-only. Cek render (authed) dijalankan di PROSES TERPISAH
 * via artisan tenant:render-check agar Auth::login tidak mencemari sesi
 * admin central yang sedang membuka wizard.
 *
 * @phpstan-type Check array{key: string, label: string, ok: bool, detail: string}
 */
class TenantHealthCheck
{
    /** Tabel inti yang wajib ada — wakil tiap modul fondasi. */
    private const CORE_TABLES = [
        'users', 'settings', 'roles', 'permissions',
        'santri', 'tagihan', 'akun', 'sumber_dana', 'jurnal',
    ];

    /**
     * Jalankan seluruh cek. $withRender butuh proses PHP terpisah —
     * matikan di lingkungan test.
     *
     * @return list<Check>
     */
    public function run(Tenant $tenant, bool $withRender = true): array
    {
        $checks = [];

        // (1) Database tenant bisa diakses.
        try {
            $tenant->run(fn () => DB::select('select 1'));
            $checks[] = $this->ok('db', 'Database tenant', 'Koneksi & query berhasil');
        } catch (\Throwable $e) {
            $checks[] = $this->fail('db', 'Database tenant', $e->getMessage());

            // Tanpa DB, semua cek tenant lain pasti gagal — berhenti di sini.
            return $checks;
        }

        $checks = array_merge($checks, $tenant->run(function () use ($tenant): array {
            $inner = [];

            // (2) Migrasi: tabel inti ada.
            $missing = array_values(array_filter(
                self::CORE_TABLES,
                fn (string $t) => ! Schema::hasTable($t),
            ));
            $inner[] = $missing === []
                ? $this->ok('tables', 'Migrasi tabel inti', count(self::CORE_TABLES).' tabel ada')
                : $this->fail('tables', 'Migrasi tabel inti', 'Tabel hilang: '.implode(', ', $missing));

            // (3) Roles & permissions ter-seed.
            $hasRole = DB::table('roles')->where('name', 'super_admin')->exists();
            $permCount = (int) DB::table('permissions')->count();
            // Ambang 40: seeder penuh ±94 permission di produksi, ±47 di env
            // test modul-minimal; seeder gagal-tengah jauh di bawah itu.
            $inner[] = ($hasRole && $permCount >= 40)
                ? $this->ok('roles', 'Role & permission', "super_admin ada, {$permCount} permission")
                : $this->fail('roles', 'Role & permission', $hasRole ? "Hanya {$permCount} permission (minimal 40)" : 'Role super_admin tidak ada');

            // (4) Baris tenant lokal (jangkar FK).
            $inner[] = DB::table('tenants')->where('id', $tenant->getKey())->exists()
                ? $this->ok('tenant_row', 'Baris tenant lokal', 'Jangkar FK tersedia')
                : $this->fail('tenant_row', 'Baris tenant lokal', 'Baris tenants lokal hilang — FK operasional akan gagal');

            // (5) Bagan akun + sumber dana (fondasi semua modul uang).
            $akun = (int) DB::table('akun')->count();
            $dana = (int) DB::table('sumber_dana')->count();
            $inner[] = ($akun > 0 && $dana > 0)
                ? $this->ok('coa', 'Bagan Akun & Sumber Dana', "{$akun} akun, {$dana} sumber dana")
                : $this->fail('coa', 'Bagan Akun & Sumber Dana', "akun={$akun}, sumber_dana={$dana} — modul keuangan akan error");

            // (6) Branding default.
            $inner[] = DB::table('settings')->where('key', 'branding_name')->exists()
                ? $this->ok('branding', 'Branding', 'branding_name terisi')
                : $this->fail('branding', 'Branding', 'Setting branding_name hilang');

            // (7) User admin dengan role super_admin.
            $adminExists = DB::table('users')
                ->join('model_has_roles', fn ($j) => $j
                    ->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', User::class))
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', 'super_admin')
                ->exists();
            $inner[] = $adminExists
                ? $this->ok('admin', 'User admin', 'super_admin siap login')
                : $this->fail('admin', 'User admin', 'Belum ada user dengan role super_admin');

            return $inner;
        }));

        // (8) Aktivasi modul (CENTRAL).
        $modCount = TenantModule::query()
            ->where('tenant_id', $tenant->getKey())->where('is_active', true)->count();
        $checks[] = $modCount > 0
            ? $this->ok('modules', 'Aktivasi modul', "{$modCount} modul aktif")
            : $this->fail('modules', 'Aktivasi modul', 'Tidak ada modul aktif — menu tenant akan kosong');

        // (9) Kuota penyimpanan (CENTRAL).
        $checks[] = TenantStorage::query()->where('tenant_id', $tenant->getKey())->exists()
            ? $this->ok('storage', 'Kuota penyimpanan', 'Record kuota ada')
            : $this->fail('storage', 'Kuota penyimpanan', 'Record TenantStorage hilang');

        // (10) Render panel admin TER-AUTENTIKASI (< 500). Proses terpisah —
        // menangkap regresi konfigurasi panel (mis. jebakan ikon grup Filament).
        if ($withRender) {
            $checks[] = $this->renderCheck($tenant);
        }

        return $checks;
    }

    /** @return array{key: string, label: string, ok: bool, detail: string} */
    private function renderCheck(Tenant $tenant): array
    {
        try {
            $result = Process::timeout(60)->run([
                PHP_BINARY, base_path('artisan'), 'tenant:render-check', (string) $tenant->getKey(),
            ]);
            $out = trim($result->output());
            $status = (int) (json_decode($out, true)['status'] ?? 0);

            if ($result->successful() && $status > 0 && $status < 500) {
                return $this->ok('render', 'Render panel /admin (authed)', "HTTP {$status}");
            }

            return $this->fail('render', 'Render panel /admin (authed)', $status > 0 ? "HTTP {$status}" : ($out !== '' ? $out : trim($result->errorOutput())));
        } catch (\Throwable $e) {
            return $this->fail('render', 'Render panel /admin (authed)', $e->getMessage());
        }
    }

    /** @return array{key: string, label: string, ok: bool, detail: string} */
    private function ok(string $key, string $label, string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'ok' => true, 'detail' => $detail];
    }

    /** @return array{key: string, label: string, ok: bool, detail: string} */
    private function fail(string $key, string $label, string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'ok' => false, 'detail' => $detail];
    }
}
