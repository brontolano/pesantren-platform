<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Modules\Asrama\Models\BlokAsrama;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Asrama\Models\PenempatanAsrama;
use Modules\Asrama\Services\AsramaImportService;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Import penempatan asrama: blok/kamar auto-create, penempatan via
 * AsramaService (gerbang tulis), dry-run tanpa tulis, guard kapasitas,
 * NIS tak dikenal & dobel ditolak per-baris.
 */
class AsramaImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('asr', 'Pesantren Asrama');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function santri(string $nis, string $nama): Santri
    {
        return Santri::create([
            'tenant_id' => $this->tenant->id,
            'nis' => $nis,
            'nama' => $nama,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status' => 'aktif',
        ]);
    }

    /** @param list<string> $baris */
    private function csv(array $baris): string
    {
        $path = tempnam(sys_get_temp_dir(), 'asrama-import-').'.csv';
        file_put_contents($path, implode("\n", $baris)."\n");

        return $path;
    }

    public function test_import_membuat_blok_kamar_dan_penempatan(): void
    {
        $this->santri('1001', 'Ahmad');
        $this->santri('1002', 'Bilal');

        $file = $this->csv([
            'nis,blok,kamar,kapasitas,gender_blok,tanggal_masuk',
            '1001,Al-Fath,A1,4,putra,2026-06-01',
            '1002,Al-Fath,A1,4,putra,2026-06-01',
        ]);

        $summary = app(AsramaImportService::class)->import($this->tenant, $file);

        $this->assertSame(2, $summary['imported']);
        $this->assertSame(0, $summary['failed']);
        $this->assertSame(1, $summary['created_blok']);
        $this->assertSame(1, $summary['created_kamar']);

        $blok = BlokAsrama::query()->where('nama_blok', 'Al-Fath')->first();
        $this->assertNotNull($blok);
        $this->assertSame('putra', $blok->gender);

        $kamar = KamarAsrama::query()->where('nomor_kamar', 'A1')->first();
        $this->assertNotNull($kamar);
        $this->assertSame(4, (int) $kamar->kapasitas);

        $this->assertSame(2, PenempatanAsrama::query()->where('is_current', true)->count());
    }

    public function test_dry_run_tidak_menulis_apapun(): void
    {
        $this->santri('1001', 'Ahmad');

        $file = $this->csv([
            'nis,blok,kamar',
            '1001,Al-Fath,A1',
        ]);

        $summary = app(AsramaImportService::class)->import($this->tenant, $file, dryRun: true);

        $this->assertSame(1, $summary['imported']);
        $this->assertSame(1, $summary['created_blok']);
        $this->assertSame(1, $summary['created_kamar']);
        $this->assertTrue($summary['dry_run']);

        $this->assertSame(0, BlokAsrama::query()->count());
        $this->assertSame(0, KamarAsrama::query()->count());
        $this->assertSame(0, PenempatanAsrama::query()->count());
    }

    public function test_guard_kapasitas_dan_nis_tak_dikenal_dan_dobel(): void
    {
        $this->santri('1001', 'Ahmad');
        $this->santri('1002', 'Bilal');
        $this->santri('1003', 'Coki');

        $file = $this->csv([
            'nis,blok,kamar,kapasitas',
            '1001,Al-Fath,A1,2',
            '1002,Al-Fath,A1,2',
            '1003,Al-Fath,A1,2',   // kamar penuh (kapasitas 2)
            '9999,Al-Fath,A2,2',   // NIS tak dikenal
            '1001,Al-Fath,A2,2',   // dobel di file
        ]);

        $summary = app(AsramaImportService::class)->import($this->tenant, $file);

        $this->assertSame(2, $summary['imported']);
        $this->assertSame(3, $summary['failed']);

        $pesan = implode(' | ', array_column($summary['errors'], 'error'));
        $this->assertStringContainsString('penuh', $pesan);
        $this->assertStringContainsString('tidak ditemukan', $pesan);
        $this->assertStringContainsString('dobel', $pesan);

        $this->assertSame(2, PenempatanAsrama::query()->where('is_current', true)->count());
    }

    public function test_penempatan_ulang_menutup_penempatan_lama(): void
    {
        $this->santri('1001', 'Ahmad');

        $svc = app(AsramaImportService::class);
        $svc->import($this->tenant, $this->csv(['nis,blok,kamar', '1001,Al-Fath,A1']));
        $svc->import($this->tenant, $this->csv(['nis,blok,kamar', '1001,Al-Fath,A2']));

        $this->assertSame(1, PenempatanAsrama::query()->where('is_current', true)->count());
        $this->assertSame(1, PenempatanAsrama::query()->where('is_current', false)->where('status', 'keluar')->count());

        $aktif = PenempatanAsrama::query()->where('is_current', true)->first();
        $this->assertSame('A2', $aktif->kamar?->nomor_kamar);
    }
}
