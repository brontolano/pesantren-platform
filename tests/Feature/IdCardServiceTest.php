<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\IdCardService;
use RuntimeException;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** ID Card santri (PDF+visual) — render kartu + QR NIS via F8. */
class IdCardServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'santri', 'kelas', 'settings']);
        $this->truncatePpdbTables(['santri', 'kelas', 'domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    public function test_html_kartu_berisi_identitas_dan_qr(): void
    {
        $this->boot('idc1');
        $a = Santri::create(['nis' => 'IDC-001', 'nama' => 'Ahmad Kartu', 'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif', 'tahun_angkatan' => '2026/2027']);
        $b = Santri::create(['nis' => 'IDC-002', 'nama' => 'Budi Kartu', 'tanggal_lahir' => '2012-02-02', 'jenis_kelamin' => 'L', 'status' => 'aktif']);

        $html = app(IdCardService::class)->html(collect([$a, $b]));

        $this->assertStringContainsString('Ahmad Kartu', $html);
        $this->assertStringContainsString('Budi Kartu', $html);
        $this->assertStringContainsString('IDC-001', $html);
        $this->assertStringContainsString('KARTU IDENTITAS SANTRI', $html);
        // QR inline data-URI per kartu (chillerlan SVG base64).
        $this->assertSame(2, substr_count($html, 'data:image/svg+xml'));
        tenancy()->end();
    }

    public function test_kumpulan_kosong_ditolak(): void
    {
        $this->boot('idc2');
        $this->expectException(RuntimeException::class);
        try {
            app(IdCardService::class)->html(collect());
        } finally {
            tenancy()->end();
        }
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
