<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Modules\Kesantrian\Models\Santri;
use Modules\PPDB\Models\CalonSantri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** API direktori publik (kontrak legacy api.asy-syifaa.com). */
class PublicDirectoryApiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'santri', 'calon_santri']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            ['name' => $tenant->name, 'is_active' => true, 'data' => json_encode([]),
                'created_at' => now(), 'updated_at' => now()],
        );

        return $tenant;
    }

    private function get_(Tenant $tenant, string $path)
    {
        $host = $tenant->id.'.localhost';

        return $this->call('GET', "http://{$host}{$path}", [], [], [], [
            'HTTP_HOST' => $host, 'SERVER_NAME' => $host,
        ]);
    }

    public function test_selection_results_hanya_yang_sudah_diputuskan_dan_search(): void
    {
        $tenant = $this->boot('pd1');
        CalonSantri::create(['nomor_pendaftaran' => 'PSB-001', 'nama' => 'Lulus Satu',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status_pendaftaran' => 'lulus',
            'asal_sekolah' => 'SDN 1', 'tahun_ajaran' => '2026/2027']);
        CalonSantri::create(['nomor_pendaftaran' => 'PSB-002', 'nama' => 'Masih Proses',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'P', 'status_pendaftaran' => 'pending']);
        tenancy()->end();

        $res = $this->get_($tenant, '/api/v1/ppdb/selection-results');
        $res->assertOk();
        $res->assertJsonPath('ok', true);
        $res->assertJsonCount(1, 'data');
        $res->assertJsonPath('data.0.registration_number', 'PSB-001');
        $res->assertJsonPath('data.0.status', 'lulus');
        $res->assertJsonMissing(['student_name' => 'Masih Proses']);

        $this->get_($tenant, '/api/v1/ppdb/selection-results?search=PSB-001')->assertJsonCount(1, 'data');
        $this->get_($tenant, '/api/v1/ppdb/selection-results?search=ZZZ')->assertJsonCount(0, 'data');
    }

    public function test_students_filter_status_dan_gender_tanpa_data_pribadi(): void
    {
        $tenant = $this->boot('pd2');
        Santri::create(['nama' => 'Aktif L', 'nis' => 'PD1', 'status' => 'aktif',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L']);
        Santri::create(['nama' => 'Aktif P', 'nis' => 'PD2', 'status' => 'aktif',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'P']);
        Santri::create(['nama' => 'Sudah Alumni', 'nis' => 'PD3', 'status' => 'lulus',
            'tanggal_lahir' => '2010-01-01', 'jenis_kelamin' => 'L']);
        tenancy()->end();

        $aktif = $this->get_($tenant, '/api/public/students?status=aktif&gender=L');
        $aktif->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.full_name', 'Aktif L');
        $this->assertArrayNotHasKey('nis', $aktif->json('data.0')); // tanpa data pribadi

        $this->get_($tenant, '/api/public/students?status=alumni')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Sudah Alumni');
    }

    public function test_staff_dan_guardians_selalu_ok_meski_kosong(): void
    {
        $tenant = $this->boot('pd3');
        tenancy()->end();

        $this->get_($tenant, '/api/public/staff')->assertOk()->assertJsonPath('ok', true);
        $this->get_($tenant, '/api/public/guardians')->assertOk()->assertJsonPath('ok', true);
    }
}
