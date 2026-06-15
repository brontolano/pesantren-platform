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
        $this->assertArrayHasKey('kesantrian', $units);
        $this->assertArrayHasKey('akademik', $units);

        $this->assertSame(0, UnitSetupRegistry::progress('kesantrian')['selesai']);
        $this->assertSame(0, UnitSetupRegistry::progress('akademik')['selesai']);
        $this->assertFalse(UnitSetupRegistry::progress('akademik')['tuntas']);

        // unit tak dikenal → aman, 0.
        $this->assertSame(0, UnitSetupRegistry::progress('entah')['total']);
    }

    public function test_data_lengkap_membuat_unit_tuntas(): void
    {
        // Akademik
        Lembaga::create(['tenant_id' => $this->tenant->id, 'nama' => 'Madrasah', 'jenjang' => 'MA']);
        PeriodeAkademik::create([
            'tenant_id' => $this->tenant->id, 'nama' => '2026/2027 Ganjil',
            'tahun_akademik' => '2026/2027', 'semester' => 'ganjil', 'is_active' => true,
            'tanggal_mulai' => '2026-07-14', 'tanggal_akhir' => '2026-12-31',
        ]);
        $kelas = Kelas::create(['tenant_id' => $this->tenant->id, 'nama_kelas' => 'VII-A', 'tingkat' => '7']);
        $pelajaran = Pelajaran::create(['tenant_id' => $this->tenant->id, 'nama' => 'Nahwu', 'kode' => 'NHW']);
        JadwalPelajaran::create([
            'tenant_id' => $this->tenant->id, 'kelas_id' => $kelas->id, 'pelajaran_id' => $pelajaran->id,
            'hari' => 'senin', 'jam_mulai' => '07:00', 'jam_selesai' => '08:00',
        ]);

        $this->assertTrue(UnitSetupRegistry::progress('akademik')['tuntas'], 'akademik harus tuntas');

        // Kesantrian
        Setting::updateOrCreate(['key' => 'branding_name'], ['value' => ['text' => 'Pondok Contoh']]);
        Santri::create([
            'tenant_id' => $this->tenant->id, 'nis' => '001', 'nama' => 'Ahmad',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'agama' => 'Islam', 'status' => 'aktif',
        ]);
        $wali = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Wali', 'phone' => '6281200000001', 'password' => 'x12345678',
        ]);
        $wali->assignRole('wali');

        $prog = UnitSetupRegistry::progress('kesantrian');
        $this->assertTrue($prog['tuntas'], 'kesantrian harus tuntas');
        $this->assertSame(100, $prog['persen']);
    }
}
