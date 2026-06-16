<?php

namespace Tests\Feature;

use App\Filament\Pages\CadanganPemulihan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRecoveryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use ReflectionMethod;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Cadangan & Pemulihan MANDIRI pondok (panel tenant):
 *  - GERBANG: hanya super_admin BER-TENANT yang boleh (akun platform ditolak).
 *  - PATH STORAGE: panggilan via tenancy()->central() = tunggal-sufiks (bukan
 *    ganda) → backup/restore/clear FILE menyasar folder yang benar.
 *  - ANTI-TERKUNCI: reset mempertahankan email + kata sandi pemicu (login biasa).
 *
 * Backup shell (pg_dump/tar) di-stub (pipe Symfony Process tak andal di Windows);
 * logika destruktif tetap nyata pada DB FISIK tenant ini (bukan DB test bersama).
 */
class CadanganPemulihanTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions',
            'model_has_roles', 'model_has_permissions',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_gerbang_hanya_super_admin_pondok(): void
    {
        $tenant = $this->createRuntimeTenant('rec', 'Pesantren Rec');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('tenant'));

        $super = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Super Pondok',
            'email' => 'super.rec@test.local', 'password' => 'password123',
        ]);
        $super->assignRole('super_admin');

        $wali = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Wali',
            'email' => 'wali.rec@test.local', 'password' => 'password123',
        ]);
        $wali->assignRole('wali');

        // Akun PLATFORM (tenant_id null) berperan super_admin → tetap ditolak.
        // (BelongsToTenant meng-auto-isi tenant_id saat dibuat dalam konteks tenant,
        // jadi paksa null di memori untuk menguji cabang defense-in-depth gerbang.)
        $platform = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Platform',
            'email' => 'platform.rec@test.local', 'password' => 'password123',
        ]);
        $platform->assignRole('super_admin');
        $platform->tenant_id = null;

        $this->actingAs($super);
        $this->assertTrue(CadanganPemulihan::canAccess(), 'super_admin pondok boleh');

        $this->actingAs($wali);
        $this->assertFalse(CadanganPemulihan::canAccess(), 'peran biasa ditolak');

        $this->actingAs($platform);
        $this->assertFalse(CadanganPemulihan::canAccess(), 'akun platform (tenant_id null) ditolak');

        tenancy()->end();
    }

    public function test_path_storage_tunggal_sufiks_lewat_central(): void
    {
        $tenant = $this->createRuntimeTenant('recs', 'Pesantren RecS');
        tenancy()->initialize($tenant);

        $svc = app(TenantRecoveryService::class);
        $ref = new ReflectionMethod($svc, 'tenantStorageDir');
        $ref->setAccessible(true);
        $key = $tenant->getKey();
        $ganda = 'tenant'.$key.DIRECTORY_SEPARATOR.'tenant'.$key;

        // Konteks tenant: storage_path() sudah ber-sufiks → service menambah sufiks
        // lagi (GANDA) = bug. Inilah yang dihindari dengan membungkus via central().
        $diTenant = $ref->invoke($svc, $tenant);
        $this->assertStringEndsWith($ganda, $diTenant, 'konteks tenant memang sufiks ganda (bug yang dihindari)');

        // Lewat central(): storage_path() tunggal → path benar (storage/tenant<id>).
        $diCentral = tenancy()->central(fn () => $ref->invoke($svc, $tenant));
        $this->assertStringEndsWith(DIRECTORY_SEPARATOR.'tenant'.$key, $diCentral);
        $this->assertStringNotContainsString($ganda, $diCentral, 'central memperbaiki ke tunggal-sufiks');

        tenancy()->end();
    }

    public function test_reset_pertahankan_kredensial_pemicu_anti_terkunci(): void
    {
        $tenant = $this->createProvisionedTenant('rec3-'.str()->lower(str()->random(4)), 'Pesantren Rec3', 'rec3-uji.localhost');

        tenancy()->initialize($tenant);
        $super = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Kyai Admin',
            'email' => 'kyai@pondok.test', 'password' => 'rahasiaku9',
        ]);
        $super->assignRole('super_admin');
        $this->actingAs($super);

        // Stub backup shell (tanpa pg_dump/tar); reset destruktif tetap nyata.
        app()->instance(TenantRecoveryService::class, new class extends TenantRecoveryService
        {
            public function backup(Tenant $tenant): string
            {
                return sys_get_temp_dir().DIRECTORY_SEPARATOR.'fake_'.$tenant->getKey().'.sql.gz';
            }

            public function backupFiles(Tenant $tenant): ?string
            {
                return null;
            }
        });

        $page = new CadanganPemulihan;
        $ref = new ReflectionMethod($page, 'lakukanReset');
        $ref->setAccessible(true);
        $hasil = $ref->invoke($page);

        $this->assertSame('kyai@pondok.test', $hasil['admin_email']);

        // ANTI-TERKUNCI: akun pemicu tetap ada, kata sandi SEMULA, peran super_admin.
        tenancy()->initialize($tenant);
        $u = User::withoutGlobalScopes()->where('email', 'kyai@pondok.test')->first();
        $this->assertNotNull($u, 'akun pemicu wajib tetap ada setelah reset');
        $this->assertTrue(Hash::check('rahasiaku9', $u->password), 'kata sandi SEMULA dipertahankan → tak terkunci');
        $this->assertTrue($u->hasRole('super_admin'), 'peran super_admin dipertahankan');
        $this->assertSame('Kyai Admin', $u->name, 'nama semula dipertahankan');
        tenancy()->end();
    }
}
