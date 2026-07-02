<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\EmisExportService;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class EmisExportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurePpdbTestingDatabase();
        if (! Schema::connection('pgsql')->hasTable('slip_gaji')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    public function test_emis_baris_santri_format_dan_tunggakan(): void
    {
        $tenant = $this->createRuntimeTenant('emis', 'Pesantren EMIS');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();

        $santri = Santri::create(['nis' => 'S-001', 'nama' => 'Ahmad', 'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'tahun_angkatan' => '2026/2027']);
        Tagihan::create([
            'santri_id' => $santri->id, 'jenis_tagihan' => 'spp', 'nominal' => 200000,
            'nominal_akhir' => 200000, 'jatuh_tempo' => '2026-06-30', 'status' => 'belum_bayar',
        ]);

        $rows = app(EmisExportService::class)->barisSantri();

        // WP-22: 8 kolom lama tetap di depan (urutan TIDAK berubah), kolom EMIS 4.0 di-append.
        // Refs #327 (+7) dan #338 (+2 kesetaraan) di-append → 22.
        $this->assertCount(22, EmisExportService::HEADER);
        $this->assertSame(['NISN', 'NIS', 'NAMA', 'JENIS_KELAMIN', 'TAHUN_ANGKATAN', 'STATUS_SANTRI', 'STATUS_SPP', 'TUNGGAKAN'], array_slice(EmisExportService::HEADER, 0, 8));
        $this->assertCount(1, $rows);
        $this->assertSame('Ahmad', $rows[0][2]);          // NAMA
        $this->assertSame('MENUNGGAK', $rows[0][6]);      // STATUS_SPP
        $this->assertEqualsWithDelta(200000, (float) $rows[0][7], 0.01); // TUNGGAKAN
    }
}
