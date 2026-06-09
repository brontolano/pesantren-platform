<?php

namespace Tests\Feature;

use App\Models\TenantModule;
use App\Services\ModuleService;
use Database\Seeders\ModuleRegistrySeeder;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Invarian I-2 (#24) — isolasi tenant pada lapis GATING MODUL (tabel central).
 *
 * Isolasi per-model (DB tenant) sudah luas teruji (Kesantrian/Keuangan/Ledger/
 * Asrama/Tahfidz/Kesehatan/Kegiatan/CMS). Celah yang ditutup di sini: tabel
 * CENTRAL `tenant_modules` yang isolasinya bergantung filter tenant_id (bukan
 * pemisahan DB fisik) — aktivasi modul tenant A TIDAK boleh bocor ke tenant B.
 *
 * Pakai createTestTenant (insert baris langsung, TANPA memicu CreateDatabase)
 * sehingga murni menguji lapis gating central tanpa DB tenant fisik.
 */
class TenantModulIsolationTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'module_registry', 'tenant_modules',
        ]);

        $this->truncatePpdbTables(['tenant_modules', 'domains', 'tenants']);

        $this->seed(ModuleRegistrySeeder::class);
    }

    public function test_aktivasi_modul_tenant_a_tidak_bocor_ke_tenant_b(): void
    {
        $a = $this->createTestTenant('iso-a', 'Tenant A');
        $b = $this->createTestTenant('iso-b', 'Tenant B');

        // Modul released 'akademik' diaktifkan HANYA untuk A.
        TenantModule::create([
            'tenant_id' => $a->getKey(),
            'module_name' => 'akademik',
            'is_active' => true,
        ]);

        $svc = new ModuleService();

        // Lapis enable (query central murni).
        $this->assertTrue($svc->isEnabledForTenant('akademik', $a->getKey()));
        $this->assertFalse($svc->isEnabledForTenant('akademik', $b->getKey()), 'Aktivasi A tak boleh bocor ke B.');

        // Lapis visibilitas (keputusan gating penuh) untuk tenant non-dev.
        $this->assertTrue($svc->isVisibleForTenant('akademik', $a), 'A (aktif) harus melihat akademik.');
        $this->assertFalse($svc->isVisibleForTenant('akademik', $b), 'B (tak aktif) tak boleh melihat akademik.');
    }

    public function test_modul_core_tetap_terlihat_semua_tenant(): void
    {
        $a = $this->createTestTenant('iso-c', 'Tenant C');
        $b = $this->createTestTenant('iso-d', 'Tenant D');

        $svc = new ModuleService();

        // Core (kesantrian) selalu tampil untuk SEMUA tenant tanpa aktivasi.
        $this->assertTrue($svc->isVisibleForTenant('kesantrian', $a));
        $this->assertTrue($svc->isVisibleForTenant('kesantrian', $b));
        $this->assertTrue($svc->isVisibleForTenant('keuangan', $b));
    }
}
