<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Beasiswa;
use Modules\Keuangan\Models\Donasi;
use Modules\Keuangan\Models\ProgramDonasi;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\SumberDana;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F4 — Portal PWA Baitul Maal (Donasi/Program Donasi/Beasiswa), sebelumnya
 * tanpa layar PWA sama sekali — bendahara wajib buka Filament admin.
 * - 403 tanpa permission view_donasi
 * - 200 dengan permission (role kepala_keuangan)
 * - Konfirmasi donasi benar2 posting jurnal (reuse DonasiService)
 * - Program & Beasiswa benar2 tersimpan ke DB
 */
class BaitulMaalModulTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    private SumberDana $sumberDana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'akun', 'sumber_dana', 'periode_akuntansi', 'jurnal', 'jurnal_baris',
            'donasi', 'program_donasi', 'beasiswa', 'santri',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('f4baitulmaal', 'Pesantren F4 Baitul Maal');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);
        (new LedgerSeeder)->run();
        $this->sumberDana = SumberDana::create([
            'tenant_id' => $this->tenant->id, 'kode_dana' => 'ZIS', 'nama_sumber' => 'Zakat Infak Sedekah',
            'kategori' => 'donasi', 'kehalalan' => 'halal', 'is_active' => true,
        ]);
        tenancy()->end();
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_akses_ditolak_403_tanpa_permission_view_donasi(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Guru Biasa', 'email' => 'guru-nonbaitulmaal@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('guru');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $this->actingAs($user)->get("http://{$host}/baitul-maal", $this->headers($host))->assertStatus(403);
    }

    public function test_akses_diterima_200_dengan_role_kepala_keuangan(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Kepala Keuangan', 'email' => 'kk@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('kepala_keuangan');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/baitul-maal", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('BaitulMaal/Beranda'));
    }

    public function test_konfirmasi_donasi_menerbitkan_jurnal_dan_mengubah_status(): void
    {
        tenancy()->initialize($this->tenant);

        $program = ProgramDonasi::create([
            'tenant_id' => $this->tenant->id, 'nama' => 'Renovasi Masjid', 'target_nominal' => 50000000,
            'sumber_dana_id' => $this->sumberDana->id, 'aktif' => true,
        ]);
        $donasi = Donasi::create([
            'tenant_id' => $this->tenant->id, 'program_donasi_id' => $program->id, 'nama_donatur' => 'Hamba Allah',
            'nominal' => 500000, 'status' => Donasi::STATUS_MENUNGGU,
        ]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bendahara Konfirmasi', 'email' => 'bendahara-konfirmasi@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('kepala_keuangan');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->post("http://{$host}/baitul-maal/donasi/{$donasi->id}/konfirmasi", [], $this->headers($host));
        $response->assertRedirect();

        tenancy()->initialize($this->tenant);
        $donasi->refresh();
        $this->assertSame(Donasi::STATUS_DIKONFIRMASI, $donasi->status);
        $this->assertNotNull($donasi->jurnal_id);
        tenancy()->end();
    }

    public function test_buat_program_donasi_baru_tersimpan(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bendahara Program', 'email' => 'bendahara-program@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('kepala_keuangan');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->post("http://{$host}/baitul-maal/program", [
            'nama' => 'Beasiswa Yatim', 'target_nominal' => 20000000, 'sumber_dana_id' => $this->sumberDana->id,
        ], $this->headers($host));
        $response->assertRedirect();

        tenancy()->initialize($this->tenant);
        $this->assertSame(1, ProgramDonasi::where('nama', 'Beasiswa Yatim')->count());
        tenancy()->end();
    }

    public function test_catat_beasiswa_santri_tersimpan(): void
    {
        tenancy()->initialize($this->tenant);

        $santri = Santri::create([
            'tenant_id' => $this->tenant->id, 'nis' => '2026099', 'nama' => 'Santri Beasiswa',
            'tanggal_lahir' => '2011-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bendahara Beasiswa', 'email' => 'bendahara-beasiswa@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('kepala_keuangan');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->post("http://{$host}/baitul-maal/beasiswa", [
            'santri_id' => $santri->id, 'sumber_dana_id' => $this->sumberDana->id,
            'nominal_per_bulan' => 300000, 'mulai' => now()->toDateString(),
        ], $this->headers($host));
        $response->assertRedirect();

        tenancy()->initialize($this->tenant);
        $this->assertSame(1, Beasiswa::where('santri_id', $santri->id)->count());
        tenancy()->end();
    }
}
