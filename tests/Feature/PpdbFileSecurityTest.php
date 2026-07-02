<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\CalonSantriDokumen;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F2 — keamanan akses berkas PPDB: hanya PEMILIK (user_id cocok) atau staf
 * manage_santri yang boleh membuka dokumen/bukti; penyusup ditolak 403;
 * URL signed menolak tanda tangan tidak sah.
 */
class PpdbFileSecurityTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'calon_santri', 'calon_santri_dokumen',
            'roles', 'permissions', 'role_has_permissions', 'model_has_roles', 'model_has_permissions',
        ]);
        $this->truncatePpdbTables(['calon_santri_dokumen', 'calon_santri', 'domains', 'tenants']);
    }

    /** @return array{0:Tenant,1:User,2:User,3:User,4:CalonSantri,5:CalonSantriDokumen} */
    private function boot(string $prefix): array
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new RolesAndPermissionsSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $mk = fn (string $n) => User::withoutGlobalScopes()->create([
            'name' => $n, 'email' => $n.'@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);
        $pemilik = $mk('pemilik');
        $penyusup = $mk('penyusup');
        $staf = $mk('staf');
        $staf->assignRole('tu'); // punya manage_santri

        $calon = CalonSantri::create([
            'nomor_pendaftaran' => 'SEC-001', 'nama' => 'Calon Sec',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L',
            'status_pendaftaran' => 'baru', 'user_id' => $pemilik->id,
        ]);

        Storage::fake(config('filesystems.default', 'local'));
        $disk = config('filesystems.default', 'local');
        Storage::disk($disk)->put('ppdb-doc/rahasia.pdf', '%PDF-rahasia');
        $dok = CalonSantriDokumen::create([
            'calon_santri_id' => $calon->id, 'jenis_dokumen' => 'kk',
            'file_url' => 'ppdb-doc/rahasia.pdf', 'nama_file' => 'kk.pdf',
        ]);

        return [$tenant, $pemilik, $penyusup, $staf, $calon, $dok];
    }

    public function test_dokumen_hanya_pemilik_dan_staf_yang_boleh(): void
    {
        [$tenant, $pemilik, $penyusup, $staf, , $dok] = $this->boot('sec1');
        $url = "http://{$tenant->id}.localhost/ppdb/file/dokumen/{$dok->id}";

        $this->actingAs($pemilik)->get($url)->assertOk();
        $this->actingAs($penyusup)->get($url)->assertForbidden();
        $this->actingAs($staf)->get($url)->assertOk();
        tenancy()->end();
    }

    public function test_dokumen_orphan_tanpa_pemilik_ditolak_untuk_non_staf(): void
    {
        [$tenant, , $penyusup, , $calon, $dok] = $this->boot('sec2');
        $calon->update(['user_id' => null]); // dokumen tanpa pemilik ter-link

        $this->actingAs($penyusup)
            ->get("http://{$tenant->id}.localhost/ppdb/file/dokumen/{$dok->id}")
            ->assertForbidden();
        tenancy()->end();
    }

    public function test_signed_doc_menolak_tanda_tangan_tidak_sah(): void
    {
        [$tenant, , , , $calon] = $this->boot('sec3');

        // Tanpa signature → 403 (middleware signed).
        $this->get("http://{$tenant->id}.localhost/ppdb/dok/{$calon->id}/kk?signature=palsu&expires=9999999999")
            ->assertForbidden();
        tenancy()->end();
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
