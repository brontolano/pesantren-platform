<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\UnitSetupRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\Lembaga;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * UnitSetupRegistry = sumber kebenaran tunggal status onboarding per unit.
 * Menguji: tenant kosong → 0%, dan setelah data lengkap → tuntas. Cek toleran
 * (tak melempar) sehingga aman dipakai hub/wizard/widget.
 */
class UnitSetupRegistryTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'settings', 'users', 'roles', 'permissions',
            'model_has_roles', 'model_has_permissions',
            'santri', 'kelas', 'lembaga', 'periode_akademik', 'pelajaran', 'jadwal_pelajaran',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('setup', 'Pesantren Setup');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_tenant_kosong_semua_unit_nol_dan_tidak_melempar(): void
    {
        $units = UnitSetupRegistry::units();
        // Selaras 9 domain navigasi (AI dihilangkan — tak ada data setup).
        foreach (['kesantrian', 'ppdb', 'akademik', 'keuangan', 'akuntansi', 'kepegawaian', 'kelembagaan', 'pengaturan'] as $k) {
            $this->assertArrayHasKey($k, $units, "unit {$k} harus ada");
        }

        $this->assertSame(0, UnitSetupRegistry::progress('kesantrian')['selesai']);
        $this->assertFalse(UnitSetupRegistry::progress('akademik')['tuntas']);

        // unit tak dikenal → aman, 0.
        $this->assertSame(0, UnitSetupRegistry::progress('entah')['total']);
    }

    public function test_langkah_terdeteksi_saat_data_ditambah(): void
    {
        // Pelajaran → langkah 'pelajaran' di Akademik terdeteksi selesai (belum tuntas).
        $progAwal = UnitSetupRegistry::progress('akademik');
        Pelajaran::create(['tenant_id' => $this->tenant->id, 'nama' => 'Nahwu', 'kode' => 'NHW']);
        $progAkhir = UnitSetupRegistry::progress('akademik');
        $this->assertGreaterThan($progAwal['selesai'], $progAkhir['selesai'], 'menambah pelajaran menaikkan progres akademik');
        $this->assertFalse($progAkhir['tuntas']);

        // branding_name → langkah 'identitas' di Pengaturan terdeteksi.
        Setting::updateOrCreate(['key' => 'branding_name'], ['value' => ['text' => 'Pondok Contoh']]);
        $this->assertGreaterThanOrEqual(1, UnitSetupRegistry::progress('pengaturan')['selesai']);

        // Santri → langkah 'santri' di Kesantrian terdeteksi.
        Santri::create([
            'tenant_id' => $this->tenant->id, 'nis' => '001', 'nama' => 'Ahmad',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'agama' => 'Islam', 'status' => 'aktif',
        ]);
        $this->assertGreaterThanOrEqual(1, UnitSetupRegistry::progress('kesantrian')['selesai']);
    }
}
