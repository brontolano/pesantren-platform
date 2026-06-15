<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Models\HalaqahSantri;
use Modules\Tahfidz\Services\HalaqahImportService;
use Modules\Tahfidz\Services\HalaqahSantriImportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Importer keanggotaan halaqah: santri wajib ada, halaqah wajib ada (resolve by
 * nama), idempoten (anggota aktif dilewati), TIDAK menutup keanggotaan lain,
 * dry-run tak menulis, joined_at default hari ini bila kosong.
 */
class HalaqahSantriImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'halaqah', 'halaqah_santri',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('hks', 'Pesantren Keanggotaan');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

        User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Ustadz Musyrif',
            'email' => 'musyrif@test.local', 'password' => 'x12345678',
        ]);
        foreach (['001', '002'] as $nis) {
            Santri::create([
                'tenant_id' => $this->tenant->id, 'nis' => $nis, 'nama' => 'Santri '.$nis,
                'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'agama' => 'Islam', 'status' => 'aktif',
            ]);
        }

        // master halaqah lewat importer yang sudah terbukti
        $h = $this->csv(['nama,musyrif', 'Halaqah A,musyrif@test.local', 'Halaqah B,musyrif@test.local']);
        app(HalaqahImportService::class)->import($this->tenant, $h, false);
        @unlink($h);
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
        $path = tempnam(sys_get_temp_dir(), 'hks').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_anggota_masuk_lalu_idempoten(): void
    {
        $file = $this->csv(['nis,halaqah,tanggal_gabung', '001,Halaqah A,2026-01-15']);

        $hasil = app(HalaqahSantriImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);

        $anggota = HalaqahSantri::query()->where('tenant_id', $this->tenant->id)->whereNull('left_at')->first();
        $this->assertNotNull($anggota);
        $this->assertSame('2026-01-15', $anggota->joined_at->toDateString());

        // Re-run → idempoten (anggota aktif dilewati, tidak dobel).
        $ulang = app(HalaqahSantriImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(1, $ulang['skipped']);
        $this->assertSame(1, HalaqahSantri::query()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }

    public function test_nis_dan_halaqah_tak_dikenal_ditolak(): void
    {
        $file = $this->csv([
            'nis,halaqah',
            '999,Halaqah A',   // nis tak ada → gagal
            '002,Halaqah Z',   // halaqah tak ada → gagal
        ]);

        $hasil = app(HalaqahSantriImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(2, $hasil['failed']);

        @unlink($file);
    }

    public function test_dry_run_tidak_menulis(): void
    {
        $file = $this->csv(['nis,halaqah', '001,Halaqah A']);
        $hasil = app(HalaqahSantriImportService::class)->import($this->tenant, $file, true);

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, HalaqahSantri::query()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }

    public function test_satu_santri_boleh_di_dua_halaqah_berbeda(): void
    {
        // Skema mengizinkan keanggotaan aktif di halaqah berbeda; import tak menutup yang lain.
        $file = $this->csv([
            'nis,halaqah',
            '001,Halaqah A',
            '001,Halaqah B',
        ]);

        $hasil = app(HalaqahSantriImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(2, $hasil['imported']);
        $this->assertSame(2, HalaqahSantri::query()->where('tenant_id', $this->tenant->id)->whereNull('left_at')->count());

        @unlink($file);
    }
}
