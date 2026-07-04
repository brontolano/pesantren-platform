<?php

namespace Tests\Feature;

use App\Filament\Forms\SantriSelect;
use App\Models\Tenant;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Dropdown santri terpusat (App\Filament\Forms\SantriSelect) — satu sumber
 * label "Nama (Kelas) — NIS" (Santri::label_pilih), dipakai di 19+ form
 * Filament lintas modul agar santri bernama sama tak tertukar.
 */
class SantriSelectTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'kelas', 'santri']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('santrisel', 'Pesantren SantriSelect');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        tenancy()->end();
    }

    public function test_label_pilih_menyertakan_kelas_dan_nis(): void
    {
        tenancy()->initialize($this->tenant);

        $kelas = Kelas::create([
            'tenant_id' => $this->tenant->id,
            'nama_kelas' => 'Ula 3',
            'tingkat' => 3,
        ]);

        $santri = Santri::create([
            'tenant_id' => $this->tenant->id,
            'nis' => '2026001',
            'nama' => 'Ahmad Fauzi',
            'tanggal_lahir' => '2010-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'kelas_id' => $kelas->id,
        ]);

        $this->assertSame('Ahmad Fauzi (Ula 3) — 2026001', $santri->label_pilih);

        tenancy()->end();
    }

    public function test_label_pilih_tetap_aman_tanpa_kelas_atau_nis(): void
    {
        tenancy()->initialize($this->tenant);

        $santri = Santri::create([
            'tenant_id' => $this->tenant->id,
            'nis' => '2026999',
            'nama' => 'Bilal Ramadhan',
            'tanggal_lahir' => '2010-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);

        $this->assertSame('Bilal Ramadhan — 2026999', $santri->label_pilih);

        tenancy()->end();
    }

    public function test_santriselect_options_berisi_label_pilih_dan_terapkan_query_modifier(): void
    {
        tenancy()->initialize($this->tenant);

        Santri::create(['tenant_id' => $this->tenant->id, 'nis' => '111', 'nama' => 'Aktif Satu', 'tanggal_lahir' => '2010-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif']);
        Santri::create(['tenant_id' => $this->tenant->id, 'nis' => '222', 'nama' => 'Nonaktif Dua', 'tanggal_lahir' => '2010-01-01', 'jenis_kelamin' => 'L', 'status' => 'lulus']);

        $select = SantriSelect::make('santri_id', fn ($q) => $q->where('status', 'aktif'));
        $options = $select->getOptions();

        $this->assertCount(1, $options);
        $this->assertSame('Aktif Satu — 111', array_values($options)[0]);

        tenancy()->end();
    }
}
