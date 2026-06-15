<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Modules\Tahfidz\Models\HafalanTahfidz;
use Modules\Tahfidz\Models\Halaqah;
use Modules\Tahfidz\Services\HafalanImportService;
use Modules\Tahfidz\Services\HalaqahImportService;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Importer Tahfidz: Halaqah (musyrif wajib resolve, idempoten by nama) &
 * Hafalan (santri wajib, idempoten by santri+surah+ayat, halaqah opsional).
 */
class TahfidzImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'halaqah', 'halaqah_santri', 'tahfidz_hafalan',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('thf', 'Pesantren Tahfidz');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

        User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Ustadz Musyrif',
            'email' => 'musyrif@test.local', 'password' => 'x12345678',
        ]);
        Santri::create([
            'tenant_id' => $this->tenant->id, 'nis' => '001', 'nama' => 'Ahmad',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'agama' => 'Islam', 'status' => 'aktif',
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
        $path = tempnam(sys_get_temp_dir(), 'thf').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_halaqah_valid_idempoten_dan_musyrif_wajib(): void
    {
        $file = $this->csv([
            'nama,musyrif,jenis',
            'Halaqah A,musyrif@test.local,tahfidz',
            'Halaqah B,tidakada@test.local,tahfidz', // musyrif tak ada → gagal
        ]);

        $hasil = app(HalaqahImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(1, $hasil['failed']);
        $this->assertSame(1, Halaqah::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        // Re-run → idempoten (Halaqah A dilewati).
        $ulang = app(HalaqahImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $ulang['imported']);

        @unlink($file);
    }

    public function test_hafalan_valid_idempoten_dan_tautkan_halaqah(): void
    {
        // siapkan halaqah utk ditautkan
        $hfile = $this->csv(['nama,musyrif', 'Halaqah A,musyrif@test.local']);
        app(HalaqahImportService::class)->import($this->tenant, $hfile, false);

        $file = $this->csv([
            'nis,halaqah,surah,ayat_awal,ayat_akhir,status',
            '001,Halaqah A,Al-Baqarah,1,5,proses',
        ]);

        $hasil = app(HafalanImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);

        $hafalan = HafalanTahfidz::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($hafalan);
        $this->assertNotNull($hafalan->halaqah_id, 'halaqah tertaut');
        $this->assertSame('proses', $hafalan->status);

        // Re-run → idempoten (santri+surah+ayat sama).
        $ulang = app(HafalanImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(1, $ulang['skipped']);
        $this->assertSame(1, HafalanTahfidz::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($hfile);
        @unlink($file);
    }

    public function test_hafalan_nis_tak_dikenal_ditolak(): void
    {
        $file = $this->csv(['nis,surah,ayat_awal,ayat_akhir', '999,An-Nas,1,6']);
        $hasil = app(HafalanImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(1, $hasil['failed']);

        @unlink($file);
    }
}
