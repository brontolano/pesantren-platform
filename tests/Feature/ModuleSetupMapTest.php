<?php

namespace Tests\Feature;

use App\Filament\Concerns\ModuleGate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ModuleService;
use App\Support\ModuleSetupMap;
use Database\Seeders\ModuleRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fase 0 self-installer modul — fondasi & keselamatan:
 *  - ModuleSetupMap: peta modul→unit + prasyarat + kesiapan (fix jebakan total==0).
 *  - ModuleService::isCore + registry tahan-banting (tak 500 saat tabel hilang).
 *  - User::isPlatformSuperAdmin (super admin PESANTREN BUKAN platform).
 *  - ModuleGate: bypass dibatasi ke platform → super admin tenant ikut gerbang.
 */
class ModuleSetupMapTest extends TestCase
{
    use RefreshDatabase;

    /** Harness untuk menguji trait gerbang modul tanpa Resource nyata. */
    private function gate(): object
    {
        return new class
        {
            use ModuleGate;

            public function vis(string $key): bool
            {
                return static::moduleVisible($key);
            }
        };
    }

    public function test_peta_unit_dan_prasyarat_antar_modul(): void
    {
        $this->assertSame('akademik', ModuleSetupMap::unit('akademik'));
        $this->assertSame('akuntansi', ModuleSetupMap::unit('ledger'));
        $this->assertNull(ModuleSetupMap::unit('kegiatan'), 'kegiatan tak punya unit setup');

        $this->assertSame(['ledger'], ModuleSetupMap::deps('pajak'));
        $this->assertSame(['ledger'], ModuleSetupMap::deps('payroll'));
        $this->assertSame([], ModuleSetupMap::deps('akademik'));
    }

    public function test_modul_tanpa_langkah_wajib_langsung_siap(): void
    {
        // ppdb & kesehatan wajib=[] → siap begitu dipasang (fix jebakan total==0).
        $this->assertTrue(ModuleSetupMap::ready('ppdb'));
        $this->assertTrue(ModuleSetupMap::ready('kesehatan'));
        // Modul tak dipetakan (mis. kegiatan) → tak punya unit → langsung siap.
        $this->assertTrue(ModuleSetupMap::ready('kegiatan'));
    }

    public function test_modul_berlangkah_wajib_belum_siap_tanpa_data(): void
    {
        // akademik wajib lembaga+periode; tenant kosong → belum siap.
        $this->assertFalse(ModuleSetupMap::ready('akademik'));
        $this->assertNotEmpty(ModuleSetupMap::requiredSteps('akademik'));
    }

    public function test_is_core_dari_registry(): void
    {
        $this->seed(ModuleRegistrySeeder::class);
        $svc = app(ModuleService::class);

        $this->assertTrue($svc->isCore('kesantrian'));
        $this->assertTrue($svc->isCore('keuangan'));
        $this->assertFalse($svc->isCore('akademik'));
        $this->assertFalse($svc->isCore('payroll'));
    }

    public function test_registry_tahan_banting_saat_tabel_hilang(): void
    {
        // Tanpa tabel registry, gerbang TIDAK boleh melempar — anggap semua terlihat.
        Schema::dropIfExists('module_registry');

        $svc = app(ModuleService::class);
        $tenant = new Tenant;
        $tenant->id = 'pesx';

        $this->assertTrue($svc->isVisibleForTenant('payroll', $tenant));
        $this->assertTrue($svc->isVisibleForTenant('kesantrian', $tenant));
    }

    public function test_super_admin_pesantren_bukan_platform(): void
    {
        // tenant_id != null → BUKAN platform (short-circuit, tak perlu cek role).
        $tenantAdmin = new User(['tenant_id' => 'pesx']);
        $this->assertFalse($tenantAdmin->isPlatformSuperAdmin());
    }

    public function test_module_gate_tidak_bypass_tanpa_platform_admin(): void
    {
        $this->seed(ModuleRegistrySeeder::class);

        // Tanpa user platform: modul released yang tak aktif → tersembunyi
        // (sebelumnya SEMUA super_admin bypass → kini hanya platform).
        $this->assertFalse($this->gate()->vis('payroll'), 'payroll (released, nonaktif) harus tersembunyi');
        // Modul inti selalu terlihat.
        $this->assertTrue($this->gate()->vis('kesantrian'));
    }
}
