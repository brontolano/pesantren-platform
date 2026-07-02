<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\EmisExportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Validasi kontrak EmisExportService (#29) — celah yang belum diuji
 * EmisExportTest (yang hanya menguji satu baris MENUNGGAK).
 *
 * CATATAN RUANG LINGKUP: service ini ekspor terformat sederhana (santri + status
 * SPP), BUKAN konformitas penuh skema Kemenag EMIS PD-Pontren. Validasi format
 * Kemenag penuh (kolom ustadz/sarpras + kode resmi) = tugas terpisah yang butuh
 * spesifikasi resmi Kemenag (belum tersedia di repo). Di sini kita jaga
 * integritas kontrak service yang ada.
 */
class EmisExportValidationTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('tagihan')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables(['domains', 'tenants']);

        $tenant = $this->createRuntimeTenant('emisval', 'Pesantren EMIS Val');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    public function test_setiap_baris_punya_jumlah_kolom_sesuai_header(): void
    {
        Santri::create(['nis' => 'S-100', 'nama' => 'Budi', 'tanggal_lahir' => '2011-01-01', 'jenis_kelamin' => 'L']);
        Santri::create(['nis' => 'S-101', 'nama' => 'Citra', 'tanggal_lahir' => '2011-02-02', 'jenis_kelamin' => 'P']);

        $rows = app(EmisExportService::class)->barisSantri();

        $this->assertCount(2, $rows);
        $jumlahKolom = count(EmisExportService::HEADER);
        foreach ($rows as $row) {
            $this->assertCount($jumlahKolom, $row, 'Tiap baris EMIS harus sejumlah kolom HEADER (cegah drift skema).');
        }
    }

    public function test_status_lunas_dan_tunggakan_nol_saat_tidak_menunggak(): void
    {
        // Santri tanpa tagihan menunggak → LUNAS, tunggakan 0.
        $santri = Santri::create(['nis' => 'S-200', 'nama' => 'Dewi', 'tanggal_lahir' => '2011-03-03', 'jenis_kelamin' => 'P']);
        Tagihan::create([
            'santri_id' => $santri->id, 'jenis_tagihan' => 'spp', 'nominal' => 150000,
            'nominal_akhir' => 150000, 'jatuh_tempo' => '2026-06-30', 'status' => 'lunas',
        ]);

        $rows = app(EmisExportService::class)->barisSantri();

        $this->assertSame('LUNAS', $rows[0][6]);                      // STATUS_SPP
        $this->assertEqualsWithDelta(0, (float) $rows[0][7], 0.01);   // TUNGGAKAN
    }

    public function test_field_tidak_lengkap_aman_sebagai_string_kosong(): void
    {
        // Tanpa NISN & tahun_angkatan → kolom terkait '' (bukan null/error).
        Santri::create(['nis' => 'S-300', 'nama' => 'Eka', 'tanggal_lahir' => '2011-04-04', 'jenis_kelamin' => 'L']);

        $rows = app(EmisExportService::class)->barisSantri();

        $this->assertSame('', $rows[0][0]); // NISN kosong
        $this->assertSame('', $rows[0][4]); // TAHUN_ANGKATAN kosong
        $this->assertSame('Eka', $rows[0][2]);
    }
}
