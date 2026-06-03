<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Tests\TestCase;

class SantriImportCommandTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'asy-syifaa',
            'name' => 'Asy-Syifaa',
            'is_active' => true,
        ]);

        $this->tenant->domains()->create([
            'domain' => 'asy-syifaa',
        ]);
    }

    public function test_can_import_santri_from_csv_and_auto_create_kelas(): void
    {
        $file = $this->makeCsv([
            ['NIS', 'Nama Lengkap', 'Tanggal Lahir', 'Jenis Kelamin', 'Nama Kelas', 'Tahun Angkatan', 'No HP Wali'],
            ['S-001', 'Ahmad Fauzan', '2012-01-01', 'L', 'VII-A', '2026/2027', '08123456789'],
            ['S-002', 'Aisyah Zahra', '2013-02-03', 'Perempuan', 'VIII-B', '2026/2027', '08987654321'],
        ]);

        $this->artisan('santri:import', [
            'tenant' => 'asy-syifaa',
            'file' => $file,
        ])->assertExitCode(0);

        tenancy()->initialize($this->tenant);

        $this->assertEquals(2, Santri::count());
        $this->assertEquals(2, Kelas::count());
        $this->assertDatabaseHas('santri', [
            'tenant_id' => 'asy-syifaa',
            'nis' => 'S-001',
            'nama' => 'Ahmad Fauzan',
        ]);
        $this->assertDatabaseHas('kelas', [
            'tenant_id' => 'asy-syifaa',
            'nama_kelas' => 'VII-A',
            'tingkat' => '7',
        ]);

        tenancy()->end();
    }

    public function test_dry_run_does_not_persist_data(): void
    {
        $file = $this->makeCsv([
            ['nis', 'nama', 'tanggal_lahir', 'jenis_kelamin', 'kelas'],
            ['S-010', 'Dry Run', '2012-04-05', 'L', 'IX-A'],
        ]);

        $this->artisan('santri:import', [
            'tenant' => 'asy-syifaa',
            'file' => $file,
            '--dry-run' => true,
        ])->assertExitCode(0);

        tenancy()->initialize($this->tenant);
        $this->assertEquals(0, Santri::count());
        $this->assertEquals(0, Kelas::count());
        tenancy()->end();
    }

    public function test_duplicate_nis_is_skipped_and_invalid_row_fails(): void
    {
        tenancy()->initialize($this->tenant);
        Santri::create([
            'tenant_id' => 'asy-syifaa',
            'nis' => 'S-001',
            'nama' => 'Sudah Ada',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);
        tenancy()->end();

        $file = $this->makeCsv([
            ['nis', 'nama', 'tanggal_lahir', 'jenis_kelamin', 'kelas'],
            ['S-001', 'Duplikat', '2012-04-05', 'L', 'IX-A'],
            ['S-002', '', '2012-04-05', 'P', 'IX-A'],
        ]);

        $this->artisan('santri:import', [
            'tenant' => 'asy-syifaa',
            'file' => $file,
        ])->assertExitCode(1);

        tenancy()->initialize($this->tenant);
        $this->assertEquals(1, Santri::count());
        tenancy()->end();
    }

    public function test_can_import_v1_style_rows_with_excel_serial_dates_and_status_mapping(): void
    {
        $file = $this->makeCsv([
            ['Status', 'Tahun Masuk', 'Kelas', 'Kelas Detail', 'NIS', 'Nama lengkap', 'Tanggal Lahir', 'Jenis Kelamin', 'Nama Ayah Kandung', 'Nama Ibu Kandung', 'Nomor Telepon Wali'],
            ['Aktif', '2024', '1', '1-A', '24250084', 'Fadly Raihan Sabil', '40808', 'Laki-Laki', 'Adang Suhendar', 'Ratmi', '082214290545'],
            ['Pengabdian', '2022', '2', '2-B', '22230052', 'Mochamad Khoirul Azmi', '07/10/2001', 'Laki-Laki', 'Ayah', 'Ibu', '081234567890'],
            ['Tendik', '2020', '3', '3-C', '20210001', 'Staff Lama', '2010-01-02', 'Perempuan', 'Ayah 2', 'Ibu 2', '089999999999'],
        ]);

        $this->artisan('santri:import', [
            'tenant' => 'asy-syifaa',
            'file' => $file,
        ])->assertExitCode(0);

        tenancy()->initialize($this->tenant);

        $this->assertDatabaseHas('santri', [
            'tenant_id' => 'asy-syifaa',
            'nis' => '24250084',
            'nama' => 'Fadly Raihan Sabil',
            'tanggal_lahir' => '2011-09-22',
            'status' => 'aktif',
        ]);

        $this->assertDatabaseHas('santri', [
            'tenant_id' => 'asy-syifaa',
            'nis' => '22230052',
            'status' => 'lulus',
        ]);

        $this->assertDatabaseHas('santri', [
            'tenant_id' => 'asy-syifaa',
            'nis' => '20210001',
            'status' => 'keluar',
            'jenis_kelamin' => 'P',
        ]);

        $this->assertDatabaseHas('kelas', [
            'tenant_id' => 'asy-syifaa',
            'nama_kelas' => '1-A',
            'tingkat' => '1',
        ]);

        tenancy()->end();
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function makeCsv(array $rows): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('santri-import-', true) . '.csv';
        $handle = fopen($path, 'wb');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }
}
