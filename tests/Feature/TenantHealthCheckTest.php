<?php

namespace Tests\Feature;

use App\Services\TenantHealthCheck;
use App\Services\TenantProvisioner;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Health check pasca-instalasi (dipakai wizard Instalasi Tenant).
 *
 * Skenario kunci:
 *  1. Tenant ter-provision penuh → SEMUA cek hijau (tanpa render — butuh
 *     proses terpisah, sudah dicakup TenantPanelAuthedSmokeTest).
 *  2. Tenant "setengah jadi" (DB ada, provisioning belum) → cek roles/coa/
 *     admin GAGAL — inilah celah yang wizard tutup.
 */
class TenantHealthCheckTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions',
            'module_registry', 'tenant_modules', 'tenant_storage',
            'settings', 'akun', 'sumber_dana',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    public function test_tenant_terprovision_penuh_semua_cek_hijau(): void
    {
        // Modul registry harus ada agar seedModules() mengaktifkan modul released.
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ModuleRegistrySeeder', '--force' => true]);

        $tenant = $this->createRuntimeTenant('hc', 'Pesantren Health Check');
        app(TenantProvisioner::class)->provision($tenant, 'admin@hc.test');

        $checks = app(TenantHealthCheck::class)->run($tenant, withRender: false);

        $byKey = collect($checks)->keyBy('key');
        foreach (['db', 'tables', 'roles', 'tenant_row', 'coa', 'branding', 'admin', 'modules', 'storage'] as $key) {
            $this->assertTrue($byKey->has($key), "Cek '{$key}' tidak dijalankan");
            $this->assertTrue(
                $byKey[$key]['ok'],
                "Cek '{$key}' gagal pada tenant ter-provision: {$byKey[$key]['detail']}"
            );
        }
    }

    public function test_tenant_setengah_jadi_terdeteksi_gagal(): void
    {
        // DB dibuat & dimigrasi (event TenantCreated) tapi TANPA provisioning.
        $tenant = $this->createRuntimeTenant('hcraw', 'Pesantren Setengah Jadi');

        $checks = app(TenantHealthCheck::class)->run($tenant, withRender: false);
        $byKey = collect($checks)->keyBy('key');

        // DB & tabel sehat (migrasi jalan otomatis)…
        $this->assertTrue($byKey['db']['ok']);
        $this->assertTrue($byKey['tables']['ok']);

        // …tapi isi provisioning hilang — HARUS terdeteksi merah.
        $this->assertFalse($byKey['roles']['ok'], 'Roles kosong harus terdeteksi');
        $this->assertFalse($byKey['coa']['ok'], 'COA kosong harus terdeteksi');
        $this->assertFalse($byKey['admin']['ok'], 'Admin hilang harus terdeteksi');
        $this->assertFalse($byKey['storage']['ok'], 'TenantStorage hilang harus terdeteksi');
    }
}
