<?php

namespace Tests\Feature;

use App\Models\TenantModule;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Status modul AKTIF per-tenant (tabel central tenant_modules).
 *
 * CATATAN: isolasi data lintas-tenant yang SEBENARNYA (database fisik terpisah)
 * diuji di TenantPhysicalIsolationTest. Versi lama test ini memakai
 * RefreshDatabase + Tenant::create yang memicu auto-provisioning
 * (CREATE DATABASE di dalam transaksi → gagal) sehingga MERAH & memberi
 * rasa-aman palsu. Ditulis ulang memakai harness DB test kanonik
 * (InteractsWithPpdbTestDatabase) dan dipersempit ke cakupan yang masih valid.
 */
class TenantIsolationTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'tenant_modules']);
        $this->truncatePpdbTables(['tenant_modules', 'domains', 'tenants']);
    }

    public function test_status_modul_aktif_terpisah_per_tenant(): void
    {
        $idA = 'mod-a-'.Str::lower(Str::random(6));
        $idB = 'mod-b-'.Str::lower(Str::random(6));
        $this->createTestTenant($idA, 'Pesantren A', $idA.'.localhost');
        $this->createTestTenant($idB, 'Pesantren B', $idB.'.localhost');

        TenantModule::create(['tenant_id' => $idA, 'module_name' => 'Hello', 'is_active' => true]);
        TenantModule::create(['tenant_id' => $idB, 'module_name' => 'Hello', 'is_active' => false]);

        $this->assertTrue(TenantModule::isModuleActive($idA, 'Hello'), 'modul aktif di tenant A');
        $this->assertFalse(TenantModule::isModuleActive($idB, 'Hello'), 'modul nonaktif di tenant B');
        // Modul yang tak terdaftar = tidak aktif (default tertutup).
        $this->assertFalse(TenantModule::isModuleActive($idA, 'TidakAda'), 'modul tak terdaftar default nonaktif');
    }
}
