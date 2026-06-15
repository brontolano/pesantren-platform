<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\ImportColumnMapper;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Services\PelajaranImportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Mesin pemetaan kolom (fleksibilitas Level 2/3): deteksi header, auto-match,
 * normalisasi file ber-header kustom → CSV kanonik yang dipahami importer asli,
 * dan simpan/baca profil per tenant. Integrasi: file kolom beda → tetap terimpor.
 */
class ImportColumnMapperTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'settings', 'pelajaran']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('map', 'Pesantren Mapping');
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

    /** @param list<string> $baris */
    private function csv(array $baris): string
    {
        $path = tempnam(sys_get_temp_dir(), 'map').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_headers_dan_automatch(): void
    {
        $mapper = new ImportColumnMapper;
        $file = $this->csv(['Mata Pelajaran,Kode MP,Jam,Pengampu', 'Aqidah,AQD,2,']);

        $this->assertSame(['Mata Pelajaran', 'Kode MP', 'Jam', 'Pengampu'], $mapper->headers($file));

        $map = $mapper->autoMatch(
            $mapper->headers($file),
            ['nama', 'kode', 'sks', 'guru'],
            ['nama' => ['Mata Pelajaran'], 'kode' => ['Kode MP'], 'sks' => ['Jam'], 'guru' => ['Pengampu']],
        );
        $this->assertSame('Mata Pelajaran', $map['nama']);
        $this->assertSame('Kode MP', $map['kode']);
        $this->assertSame('Jam', $map['sks']);
        $this->assertSame('Pengampu', $map['guru']);

        @unlink($file);
    }

    public function test_normalisasi_lalu_importer_asli_menerima(): void
    {
        $mapper = new ImportColumnMapper;
        // File pesantren dgn nama & URUTAN kolom berbeda dari kanonik.
        $file = $this->csv(['Pengampu,Jam,Mata Pelajaran,Kode MP', ',2,Aqidah,AQD', ',3,Fiqih,FQH']);

        $mapping = ['nama' => 'Mata Pelajaran', 'kode' => 'Kode MP', 'sks' => 'Jam', 'guru' => 'Pengampu'];
        $normalized = $mapper->normalizeToCsv($file, ['nama', 'kode', 'sks', 'guru'], $mapping);

        // Importer asli (tak diubah) menerima file ternormalisasi.
        $hasil = app(PelajaranImportService::class)->import($this->tenant, $normalized, false);

        $this->assertSame(2, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);
        $aqidah = Pelajaran::withoutGlobalScopes()->where('kode', 'AQD')->first();
        $this->assertNotNull($aqidah);
        $this->assertSame('Aqidah', $aqidah->nama);

        @unlink($file);
        @unlink($normalized);
    }

    public function test_profil_disimpan_dan_dibaca_per_tenant(): void
    {
        $mapper = new ImportColumnMapper;
        $mapping = ['nama' => 'Mata Pelajaran', 'kode' => 'Kode MP'];

        $this->assertSame([], $mapper->loadProfile('pelajaran'));
        $mapper->saveProfile('pelajaran', $mapping);
        $this->assertSame($mapping, $mapper->loadProfile('pelajaran'));
    }
}
