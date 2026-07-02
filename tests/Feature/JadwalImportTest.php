<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Services\JadwalImportService;
use Modules\Kesantrian\Models\Kelas;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Import Jadwal: tautkan ke kelas/pelajaran/periode yang ada, validasi
 * hari & jam, idempoten (slot+pelajaran sama dilewati), tolak bentrok slot,
 * tolak master tak dikenal.
 */
class JadwalImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'kelas', 'pelajaran', 'periode_akademik', 'jadwal_pelajaran',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('jdw', 'Pesantren Jadwal');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

        Kelas::create(['tenant_id' => $this->tenant->id, 'nama_kelas' => 'VII-A', 'tingkat' => '7']);
        Pelajaran::create(['tenant_id' => $this->tenant->id, 'nama' => 'Nahwu', 'kode' => 'NHW']);
        Pelajaran::create(['tenant_id' => $this->tenant->id, 'nama' => 'Shorof', 'kode' => 'SHR']);
        PeriodeAkademik::create([
            'tenant_id' => $this->tenant->id, 'nama' => '2026/2027 Ganjil',
            'tahun_akademik' => '2026/2027', 'semester' => 'ganjil', 'is_active' => true,
            'tanggal_mulai' => '2026-07-14', 'tanggal_akhir' => '2026-12-31',
        ]);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    /** @param list<string> $baris */
    private function csv(array $baris): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jdw').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_import_valid_membuat_jadwal_dengan_periode_aktif(): void
    {
        $file = $this->csv([
            'kelas,pelajaran,hari,jam_mulai,jam_selesai,ruangan',
            'VII-A,NHW,senin,07:00,08:30,R1',
            'VII-A,SHR,senin,08:30,10:00,R1',
        ]);

        $hasil = app(JadwalImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(2, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);
        $this->assertSame(2, JadwalPelajaran::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        $jadwal = JadwalPelajaran::withoutGlobalScopes()->where('hari', 'senin')->where('jam_mulai', '07:00')->first();
        $this->assertNotNull($jadwal->periode_akademik_id, 'periode aktif harus ter-set');

        @unlink($file);
    }

    public function test_idempoten_dan_tolak_bentrok_slot(): void
    {
        $file = $this->csv(['kelas,pelajaran,hari,jam_mulai,jam_selesai', 'VII-A,NHW,selasa,07:00,08:00']);
        app(JadwalImportService::class)->import($this->tenant, $file, false);

        // Re-run sama → idempoten (skip).
        $ulang = app(JadwalImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(1, $ulang['skipped']);

        // Slot sama, pelajaran beda → bentrok (gagal).
        $bentrok = $this->csv(['kelas,pelajaran,hari,jam_mulai,jam_selesai', 'VII-A,SHR,selasa,07:00,08:00']);
        $hasil = app(JadwalImportService::class)->import($this->tenant, $bentrok, false);
        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(1, $hasil['failed']);

        $this->assertSame(1, JadwalPelajaran::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('hari', 'selasa')->count());

        @unlink($file);
        @unlink($bentrok);
    }

    public function test_master_tak_dikenal_dan_jam_invalid_ditolak(): void
    {
        $file = $this->csv([
            'kelas,pelajaran,hari,jam_mulai,jam_selesai',
            'XII-Z,NHW,rabu,07:00,08:00',   // kelas tak ada
            'VII-A,XXX,rabu,07:00,08:00',   // pelajaran tak ada
            'VII-A,NHW,funday,07:00,08:00', // hari invalid
            'VII-A,NHW,rabu,08:00,07:00',   // selesai < mulai
        ]);

        $hasil = app(JadwalImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(4, $hasil['failed']);

        @unlink($file);
    }
}
