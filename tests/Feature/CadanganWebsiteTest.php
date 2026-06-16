<?php

namespace Tests\Feature;

use App\Filament\Pages\CadanganWebsite;
use App\Models\Tenant;
use App\Models\User;
use App\Services\WebsiteRecoveryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Modules\CMS\Models\Article;
use Modules\Kesantrian\Models\Santri;
use ReflectionMethod;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Cadangan & Pemulihan WEBSITE (eksternal) — TERPISAH dari data internal.
 *  - GERBANG: hanya super_admin pondok.
 *  - PEMISAHAN: reset website mengosongkan tabel CMS SAJA; data operasional
 *    (santri/keuangan) & akun TIDAK tersentuh.
 *
 * Backup shell (pg_dump/tar) di-stub (pipe Symfony Process tak andal di Windows);
 * TRUNCATE tabel CMS tetap nyata pada DB FISIK tenant ini.
 */
class CadanganWebsiteTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions',
            'model_has_roles', 'model_has_permissions',
            'articles', 'galleries', 'gallery_images', 'website_settings', 'website_sections', 'santri',
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
        $tenant = $this->createRuntimeTenant('web', 'Pesantren Web');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('tenant'));

        $super = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Super', 'email' => 'super.web@test.local', 'password' => 'password123',
        ]);
        $super->assignRole('super_admin');

        $wali = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Wali', 'email' => 'wali.web@test.local', 'password' => 'password123',
        ]);
        $wali->assignRole('wali');

        $platform = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Platform', 'email' => 'platform.web@test.local', 'password' => 'password123',
        ]);
        $platform->assignRole('super_admin');
        $platform->tenant_id = null; // paksa null di memori (auto-isi BelongsToTenant)

        $this->actingAs($super);
        $this->assertTrue(CadanganWebsite::canAccess());

        $this->actingAs($wali);
        $this->assertFalse(CadanganWebsite::canAccess());

        $this->actingAs($platform);
        $this->assertFalse(CadanganWebsite::canAccess());

        tenancy()->end();
    }

    public function test_reset_website_kosongkan_cms_tanpa_sentuh_data_operasional(): void
    {
        $tenant = $this->createProvisionedTenant('web2-'.str()->lower(str()->random(4)), 'Pesantren Web2', 'web2-uji.localhost');

        tenancy()->initialize($tenant);

        // Konten WEBSITE (CMS) + data OPERASIONAL berdampingan.
        Article::create(['title' => 'Halo Dunia', 'slug' => 'halo-dunia', 'content' => 'isi artikel']);
        DB::table('website_settings')->insert([
            'tenant_id' => $tenant->id, 'key' => 'tagline', 'value' => json_encode(['text' => 'Pondok Hebat']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Santri::create([
            'tenant_id' => $tenant->id, 'nis' => '001', 'nama' => 'Ahmad',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'agama' => 'Islam', 'status' => 'aktif',
        ]);
        $usersAwal = DB::table('users')->count();

        $this->assertSame(1, DB::table('articles')->count());
        $this->assertSame(1, DB::table('website_settings')->count());
        $this->assertSame(1, DB::table('santri')->count());

        // Stub backup shell; TRUNCATE CMS tetap nyata.
        app()->instance(WebsiteRecoveryService::class, new class extends WebsiteRecoveryService
        {
            public function backupDb(Tenant $tenant): string
            {
                return sys_get_temp_dir().DIRECTORY_SEPARATOR.'fake_web_'.$tenant->getKey().'.sql.gz';
            }

            public function backupFiles(Tenant $tenant): ?string
            {
                return null;
            }
        });

        app(WebsiteRecoveryService::class)->resetWebsite($tenant);

        // Konten website KOSONG...
        $this->assertSame(0, DB::table('articles')->count(), 'artikel CMS dikosongkan');
        $this->assertSame(0, DB::table('website_settings')->count(), 'pengaturan website dikosongkan');
        // ...tapi data operasional & akun UTUH (pemisahan eksternal vs internal).
        $this->assertSame(1, DB::table('santri')->count(), 'data santri TIDAK tersentuh');
        $this->assertSame($usersAwal, DB::table('users')->count(), 'akun TIDAK tersentuh');

        tenancy()->end();
    }

    public function test_restore_db_tolak_dump_di_luar_cms(): void
    {
        $svc = app(WebsiteRecoveryService::class);
        $ref = new ReflectionMethod($svc, 'validateDumpScope');
        $ref->setAccessible(true);

        // Dump KONTEN WEBSITE yang sah (hanya tabel CMS) → lolos, tak melempar.
        $ok = $this->buatDumpGz("DROP TABLE IF EXISTS public.articles;\nCREATE TABLE public.galleries (id int);\nCOPY public.website_settings (key) FROM stdin;\nALTER TABLE ONLY public.articles ADD CONSTRAINT a FOREIGN KEY (created_by) REFERENCES public.users(id);\n");
        $ref->invoke($svc, $ok);
        @unlink($ok);

        // Dump backup DATA KESELURUHAN (menyentuh tabel operasional) → DITOLAK.
        $bad = $this->buatDumpGz("CREATE TABLE public.articles (id int);\nCOPY public.santri (id) FROM stdin;\n");
        try {
            $ref->invoke($svc, $bad);
            $this->fail('Dump dengan tabel non-CMS (santri) seharusnya ditolak demi pemisahan.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('santri', $e->getMessage());
        }
        @unlink($bad);
    }

    private function buatDumpGz(string $isi): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dump').'.sql.gz';
        $fh = gzopen($path, 'wb');
        gzwrite($fh, $isi);
        gzclose($fh);

        return $path;
    }
}
