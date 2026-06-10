<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Modules\Notifikasi\Services\NotifDispatcher;
use Modules\PPDB\Models\CalonSantri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** Pengingat daftar ulang WA — sasaran tepat: lulus & belum daftar ulang. */
class IngatkanDaftarUlangTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private MockInterface $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'calon_santri']);
        $this->truncatePpdbTables(['calon_santri', 'domains', 'tenants']);

        $this->dispatcher = Mockery::spy(NotifDispatcher::class);
        $this->app->instance(NotifDispatcher::class, $this->dispatcher);
    }

    private function calon(string $nomor, string $status, ?string $regUlang = null, ?string $hp = '0811111111'): CalonSantri
    {
        return CalonSantri::create([
            'nomor_pendaftaran' => $nomor, 'nama' => 'Calon '.$nomor,
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L',
            'status_pendaftaran' => $status, 'no_hp_wali' => $hp,
            'tanggal_registrasi_ulang' => $regUlang,
        ]);
    }

    public function test_hanya_lulus_belum_daftar_ulang_yang_diingatkan(): void
    {
        $tenant = $this->createRuntimeTenant('idu1', 'Pesantren IDU');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        $target = $this->calon('IDU-001', 'lulus');                       // sasaran
        $this->calon('IDU-002', 'lulus', now()->toDateTimeString());     // sudah daftar ulang
        $this->calon('IDU-003', 'baru');                                  // belum lulus
        tenancy()->end();

        $this->artisan('ppdb:ingatkan-daftar-ulang', ['tenant' => $tenant->id])->assertSuccessful();

        $this->dispatcher->shouldHaveReceived('kirim')
            ->with('pengingat_daftar_ulang', Mockery::on(
                fn ($p) => ($p['data']['nomor_pendaftaran'] ?? null) === 'IDU-001'
                    && in_array('0811111111', $p['wa'] ?? [], true)
            ))
            ->once();
        // Total panggilan = 1 (IDU-002 & IDU-003 TIDAK diingatkan).
        $this->dispatcher->shouldHaveReceived('kirim')->once();
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
