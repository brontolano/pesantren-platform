<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\SantriAccountService;
use Modules\Notifikasi\Services\NotifDispatcher;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** App Santri — konversi santri aktif jadi akun login + tautan wali. */
class SantriAccountServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri',
            'roles', 'permissions', 'role_has_permissions', 'model_has_roles', 'model_has_permissions',
        ]);
        $this->truncatePpdbTables(['santri', 'domains', 'tenants']);
    }

    private function boot(string $prefix): Santri
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new RolesAndPermissionsSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return Santri::create([
            'nis' => strtoupper($prefix).'-77', 'nama' => 'Santri Akun',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
            'no_hp_wali' => '0899000111',
        ]);
    }

    public function test_provision_membuat_akun_role_santri_dan_idempoten(): void
    {
        $santri = $this->boot('akn1');
        $svc = app(SantriAccountService::class);

        $u1 = $svc->provision($santri);
        $this->assertTrue($u1->hasRole('santri'));
        $this->assertSame($u1->id, $santri->fresh()->user_id);
        $this->assertStringContainsString('@'.tenant('id').'.santri.localhost', $u1->email);

        // Idempoten: panggilan kedua mengembalikan user yang sama, tak menggandakan.
        $u2 = $svc->provision($santri->fresh());
        $this->assertSame($u1->id, $u2->id);
        $this->assertSame(1, User::withoutGlobalScopes()->where('email', $u1->email)->count());
        tenancy()->end();
    }

    public function test_tautkan_wali_membuat_akun_wali_dan_mengisi_wali_user_id(): void
    {
        $santri = $this->boot('akn2');
        $svc = app(SantriAccountService::class);

        $wali = $svc->tautkanWali($santri);
        $this->assertNotNull($wali);
        $this->assertTrue($wali->hasRole('wali'));
        $this->assertSame($wali->id, $santri->fresh()->wali_user_id);

        // Tanpa no_hp_wali → null tanpa melempar (best-effort).
        $santri2 = Santri::create([
            'nis' => 'AKN2-78', 'nama' => 'Tanpa Wali', 'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);
        $this->assertNull($svc->tautkanWali($santri2));
        tenancy()->end();
    }

    public function test_command_provision_massal_dan_kirim_wa(): void
    {
        $santri = $this->boot('akn3'); // 1 aktif tanpa akun, no_hp_wali terisi
        Santri::create([
            'nis' => 'AKN3-99', 'nama' => 'Sudah Punya', 'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);
        $tenantId = tenant('id');
        // Beri akun ke santri kedua agar dilewati command (idempoten).
        app(SantriAccountService::class)->provision(Santri::where('nis', 'AKN3-99')->first());
        tenancy()->end();

        $spy = \Mockery::spy(NotifDispatcher::class);
        $this->app->instance(NotifDispatcher::class, $spy);

        $this->artisan('santri:provision-akun', ['tenant' => $tenantId, '--kirim-wa' => true])
            ->assertSuccessful();

        tenancy()->initialize(Tenant::find($tenantId));
        $this->assertNotNull($santri->fresh()->user_id, 'Santri tanpa akun harus dibuatkan.');
        tenancy()->end();

        // Kredensial WA hanya utk santri yang BARU dibuatkan (1x).
        $spy->shouldHaveReceived('kirim')
            ->with('kredensial_santri', \Mockery::on(fn ($p) => in_array('0899000111', $p['wa'] ?? [], true)))
            ->once();
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
