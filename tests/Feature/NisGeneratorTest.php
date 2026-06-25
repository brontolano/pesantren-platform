<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\NisGeneratorService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** Auto-NIS: template token, counter berurut race-safe, scope reset. */
class NisGeneratorTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'santri', 'settings']);
        if (! Schema::connection('pgsql')->hasColumn('santri', 'nis_urut')) {
            Artisan::call('migrate', ['--force' => true]);
        }
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): NisGeneratorService
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            ['name' => $tenant->name, 'is_active' => true, 'data' => json_encode([]),
                'created_at' => now(), 'updated_at' => now()],
        );

        return app(NisGeneratorService::class);
    }

    private function dataSantri(string $nama): array
    {
        return ['nama' => $nama, 'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif'];
    }

    public function test_format_default_pendek_dan_ala_kemenag(): void
    {
        $svc = $this->boot('nis1');

        // Tanpa NSP → fallback pendek {TA}.{URUT:4}
        $this->assertSame('2026.0007', $svc->format(7, 2026));

        // Dengan NSP → default ala Kemenag {NSP}{TA:2}{URUT:4}
        Setting::updateOrCreate(['key' => NisGeneratorService::KEY_KODE_LEMBAGA], ['value' => '510332110001']);
        $this->assertSame('510332110001260007', $svc->format(7, 2026));

        // Template custom menang.
        Setting::updateOrCreate(['key' => NisGeneratorService::KEY_TEMPLATE], ['value' => 'STN-{TA}-{URUT:3}']);
        $this->assertSame('STN-2026-007', $svc->format(7, 2026));
        tenancy()->end();
    }

    public function test_create_santri_berurut_dan_unik(): void
    {
        $svc = $this->boot('nis2');

        $a = $svc->createSantri($this->dataSantri('Santri A'), 2026);
        $b = $svc->createSantri($this->dataSantri('Santri B'), 2026);
        $c = $svc->createSantri($this->dataSantri('Santri C'), 2026);

        $this->assertSame('2026.0001', $a->nis);
        $this->assertSame('2026.0002', $b->nis);
        $this->assertSame('2026.0003', $c->nis);
        $this->assertSame([1, 2, 3], [$a->nis_urut, $b->nis_urut, $c->nis_urut]);
        $this->assertSame(3, Santri::query()->count());

        // Pratinjau tidak menambah counter.
        $this->assertSame('2026.0004', $svc->preview(2026));
        $this->assertSame(3, Santri::query()->count());
        tenancy()->end();
    }

    public function test_scope_tahun_ajaran_reset_per_tahun_dan_global_lanjut(): void
    {
        $svc = $this->boot('nis3');

        $svc->createSantri($this->dataSantri('A26'), 2026);
        $svc->createSantri($this->dataSantri('B26'), 2026);
        // Tahun baru → counter mulai 1 lagi (scope default tahun_ajaran).
        $d = $svc->createSantri($this->dataSantri('A27'), 2027);
        $this->assertSame('2027.0001', $d->nis);

        // Scope global → lanjut dari urut tertinggi lintas tahun.
        Setting::updateOrCreate(['key' => NisGeneratorService::KEY_RESET_SCOPE], ['value' => 'global']);
        $e = $svc->createSantri($this->dataSantri('B27'), 2027);
        $this->assertSame(3, $e->nis_urut); // tertinggi global = 2 → 3
        tenancy()->end();
    }
}
