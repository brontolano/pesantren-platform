<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Models\Surat;
use Modules\Core\Services\SuratService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** Surat & Administrasi — penomoran agenda otomatis per jenis+tahun. */
class SuratServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'surat']);
        $this->truncatePpdbTables(['surat', 'domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    public function test_nomor_urut_terpisah_per_jenis_dan_tahun(): void
    {
        $this->boot('srt1');
        $svc = app(SuratService::class);

        $k1 = $svc->catat(['jenis' => 'keluar', 'tanggal_surat' => '2026-06-10', 'pihak' => 'Kemenag', 'perihal' => 'Laporan']);
        $k2 = $svc->catat(['jenis' => 'keluar', 'tanggal_surat' => '2026-06-11', 'pihak' => 'Dinas', 'perihal' => 'Undangan']);
        $m1 = $svc->catat(['jenis' => 'masuk', 'tanggal_surat' => '2026-06-12', 'pihak' => 'Yayasan', 'perihal' => 'Edaran', 'nomor_surat' => '012/YYS/VI/2026']);
        $k2027 = $svc->catat(['jenis' => 'keluar', 'tanggal_surat' => '2027-01-05', 'pihak' => 'Wali', 'perihal' => 'Pengumuman']);

        $this->assertSame([1, 2], [$k1->nomor_urut, $k2->nomor_urut], 'Keluar berurut per tahun.');
        $this->assertSame(1, $m1->nomor_urut, 'Agenda masuk terpisah dari keluar.');
        $this->assertSame(1, $k2027->nomor_urut, 'Tahun baru reset urut.');
        // Format nomor keluar: 001/PES/VI/2026 (romawi bulan).
        $this->assertSame('001/PES/VI/2026', $k1->nomor_surat);
        $this->assertSame('001/PES/I/2027', $k2027->nomor_surat);
        // Surat masuk menyimpan nomor pengirim apa adanya.
        $this->assertSame('012/YYS/VI/2026', $m1->nomor_surat);
        tenancy()->end();
    }

    public function test_surat_masuk_tanpa_nomor_ditolak_dan_kode_kustom(): void
    {
        $this->boot('srt2');
        $svc = app(SuratService::class);

        try {
            $svc->catat(['jenis' => 'masuk', 'tanggal_surat' => '2026-06-10', 'pihak' => 'X', 'perihal' => 'Y']);
            $this->fail('Surat masuk tanpa nomor harus ditolak.');
        } catch (InvalidArgumentException) {
        }

        $s = $svc->catat(['jenis' => 'keluar', 'tanggal_surat' => '2026-03-01', 'pihak' => 'Bank', 'perihal' => 'Permohonan', 'kode' => 'tu.keu']);
        $this->assertSame('001/TU.KEU/III/2026', $s->nomor_surat);
        $this->assertSame(1, Surat::where('jenis', 'keluar')->count());
        tenancy()->end();
    }

    private function seedTenantRow(Tenant $tenant): void
    {
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            ['name' => $tenant->name, 'is_active' => $tenant->is_active,
             'data' => json_encode([]), 'created_at' => now(), 'updated_at' => now()],
        );
    }
}
