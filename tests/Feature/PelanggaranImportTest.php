<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Modules\Kesantrian\Models\EskalasiDisiplin;
use Modules\Kesantrian\Models\JenisPelanggaran;
use Modules\Kesantrian\Models\PelanggaranSantri;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\PelanggaranImportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Importer pelanggaran historis (opt-in): membuat baris LANGSUNG sehingga TIDAK
 * memicu eskalasi/SP otomatis (efek samping ditekan), idempoten skip-if-exists
 * (santri+jenis+waktu), jenis/nis wajib ada, default status selesai + kronologi,
 * dry-run tak menulis.
 */
class PelanggaranImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    private int $pencatatId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'jenis_pelanggaran', 'pelanggaran_santri', 'eskalasi_disiplin',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('plg', 'Pesantren Pelanggaran');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

        $u = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Qism Keamanan',
            'email' => 'keamanan@test.local', 'password' => 'x12345678',
        ]);
        $this->pencatatId = $u->id;

        Santri::create([
            'tenant_id' => $this->tenant->id, 'nis' => '001', 'nama' => 'Ahmad',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'agama' => 'Islam', 'status' => 'aktif',
        ]);

        // Jenis berat berpoin TINGGI: kalau eskalasi sampai jalan, pasti tercipta SP.
        JenisPelanggaran::create([
            'tenant_id' => $this->tenant->id, 'nama' => 'Pelanggaran Berat', 'kategori' => 'berat',
            'poin' => 100, 'aktif' => true,
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
        $path = tempnam(sys_get_temp_dir(), 'plg').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_historis_tanpa_eskalasi_dan_idempoten(): void
    {
        $file = $this->csv([
            'nis,jenis,waktu_kejadian,status',
            '001,Pelanggaran Berat,2026-01-15 05:30,selesai',
        ]);

        $hasil = app(PelanggaranImportService::class)->import($this->tenant, $file, false, $this->pencatatId);
        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);

        $p = PelanggaranSantri::query()->where('tenant_id', $this->tenant->id)->first();
        $this->assertSame('selesai', $p->status);
        $this->assertSame($this->pencatatId, (int) $p->pencatat_id);
        $this->assertSame($this->pencatatId, (int) $p->verifikator_id);
        $this->assertNotNull($p->verified_at);

        // BUKTI efek samping ditekan: poin 100 ≥ ambang SP, tapi TIDAK ada eskalasi.
        $this->assertSame(0, EskalasiDisiplin::query()->where('tenant_id', $this->tenant->id)->count(),
            'impor historis tidak boleh menerbitkan SP/eskalasi otomatis');

        // Re-run → idempoten.
        $ulang = app(PelanggaranImportService::class)->import($this->tenant, $file, false, $this->pencatatId);
        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(1, $ulang['skipped']);
        $this->assertSame(1, PelanggaranSantri::query()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }

    public function test_default_status_selesai_dan_kronologi(): void
    {
        $file = $this->csv(['nis,jenis,waktu_kejadian', '001,Pelanggaran Berat,2026-02-01 07:00']);
        app(PelanggaranImportService::class)->import($this->tenant, $file, false, $this->pencatatId);

        $p = PelanggaranSantri::query()->where('tenant_id', $this->tenant->id)->first();
        $this->assertSame('selesai', $p->status);
        $this->assertSame('(impor data historis)', $p->kronologi);

        @unlink($file);
    }

    public function test_jenis_dan_nis_tak_dikenal_ditolak(): void
    {
        $file = $this->csv([
            'nis,jenis,waktu_kejadian',
            '001,Tidak Ada,2026-03-01 06:00',           // jenis tak ada → gagal
            '999,Pelanggaran Berat,2026-03-01 06:00',   // nis tak ada → gagal
        ]);

        $hasil = app(PelanggaranImportService::class)->import($this->tenant, $file, false, $this->pencatatId);
        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(2, $hasil['failed']);

        @unlink($file);
    }

    public function test_dry_run_tidak_menulis(): void
    {
        $file = $this->csv(['nis,jenis,waktu_kejadian', '001,Pelanggaran Berat,2026-04-01 06:00']);
        $hasil = app(PelanggaranImportService::class)->import($this->tenant, $file, true);

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, PelanggaranSantri::query()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }
}
