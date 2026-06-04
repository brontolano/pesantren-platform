<?php

namespace Tests\Feature;

use App\Filament\Pages\PpdbReport;
use App\Filament\Resources\PPDB\CalonSantriDokumenResource;
use App\Filament\Resources\PPDB\CalonSantriResource;
use App\Filament\Resources\PPDB\HasilUjianResource;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\CalonSantriDokumen;
use Modules\PPDB\Models\HasilUjian;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PpdbResourceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncatePpdbTables([
            'calon_santri_dokumen',
            'hasil_ujian',
            'calon_santri',
            'model_has_permissions',
            'model_has_roles',
            'role_has_permissions',
            'permissions',
            'roles',
            'domains',
            'tenants',
            'users',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenantA = $this->createTestTenant('asy-syifaa', 'Asy-Syifaa', 'asy-syifaa');
        $this->tenantB = $this->createTestTenant('demo', 'Demo', 'demo');
    }

    public function test_tu_can_access_ppdb_resources_and_report_routes(): void
    {
        tenancy()->initialize($this->tenantA);

        $user = User::create([
            'name' => 'TU Asy-Syifaa',
            'email' => 'tu@asy-syifaa.localhost',
            'password' => 'password',
        ]);
        $user->assignRole('tu');

        $this->actingAs($user);

        $this->assertTrue(CalonSantriResource::canAccess());
        $this->assertTrue(CalonSantriDokumenResource::canAccess());
        $this->assertTrue(HasilUjianResource::canAccess());
        $this->assertTrue(PpdbReport::canAccess());

        $this->assertTrue(Route::has('filament.admin.resources.ppdb.calon-santri.index'));
        $this->assertTrue(Route::has('filament.admin.resources.ppdb.dokumen.index'));
        $this->assertTrue(Route::has('filament.admin.resources.ppdb.hasil-ujian.index'));
        $this->assertTrue(Route::has('filament.admin.pages.ppdb.report'));

        tenancy()->end();
    }

    public function test_ppdb_resources_only_query_current_tenant_records(): void
    {
        tenancy()->initialize($this->tenantA);

        $calonA = CalonSantri::create([
            'nomor_pendaftaran' => 'PPDB-2026-001',
            'nama' => 'Calon A',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'no_hp_wali' => '081111111111',
            'program_diminati' => 'Reguler',
            'status_pendaftaran' => 'pending',
        ]);
        CalonSantriDokumen::create([
            'calon_santri_id' => $calonA->id,
            'jenis_dokumen' => 'akta',
            'file_url' => 'https://example.test/akta-a.pdf',
        ]);
        HasilUjian::create([
            'calon_santri_id' => $calonA->id,
            'kategori_ujian' => 'tulis',
            'nilai' => 88.5,
            'catatan' => 'Baik',
        ]);

        tenancy()->end();

        tenancy()->initialize($this->tenantB);

        $calonB = CalonSantri::create([
            'nomor_pendaftaran' => 'PPDB-2026-002',
            'nama' => 'Calon B',
            'tanggal_lahir' => '2012-02-02',
            'jenis_kelamin' => 'P',
            'no_hp_wali' => '082222222222',
            'program_diminati' => 'Tahfidz',
            'status_pendaftaran' => 'lulus',
        ]);
        CalonSantriDokumen::create([
            'calon_santri_id' => $calonB->id,
            'jenis_dokumen' => 'ijazah',
            'file_url' => 'https://example.test/ijazah-b.pdf',
        ]);
        HasilUjian::create([
            'calon_santri_id' => $calonB->id,
            'kategori_ujian' => 'wawancara',
            'nilai' => 91,
            'catatan' => 'Sangat baik',
        ]);

        $this->assertCount(1, CalonSantriResource::getEloquentQuery()->get());
        $this->assertSame('Calon B', CalonSantriResource::getEloquentQuery()->first()->nama);

        $this->assertCount(1, CalonSantriDokumenResource::getEloquentQuery()->get());
        $this->assertSame('ijazah', CalonSantriDokumenResource::getEloquentQuery()->first()->jenis_dokumen);

        $this->assertCount(1, HasilUjianResource::getEloquentQuery()->get());
        $this->assertSame('wawancara', HasilUjianResource::getEloquentQuery()->first()->kategori_ujian);

        tenancy()->end();
    }

    public function test_user_without_santri_permissions_cannot_access_ppdb_resources_or_report(): void
    {
        tenancy()->initialize($this->tenantA);

        $user = User::create([
            'name' => 'Wali',
            'email' => 'wali@asy-syifaa.localhost',
            'password' => 'password',
        ]);
        $user->assignRole('wali');

        $this->actingAs($user);

        $this->assertFalse(CalonSantriResource::canAccess());
        $this->assertFalse(CalonSantriDokumenResource::canAccess());
        $this->assertFalse(HasilUjianResource::canAccess());
        $this->assertFalse(PpdbReport::canAccess());

        tenancy()->end();
    }

    public function test_ppdb_report_counts_only_current_tenant(): void
    {
        tenancy()->initialize($this->tenantA);

        CalonSantri::create([
            'nomor_pendaftaran' => 'PPDB-2026-003',
            'nama' => 'Pending A',
            'tanggal_lahir' => '2012-03-03',
            'jenis_kelamin' => 'L',
            'no_hp_wali' => '083333333333',
            'program_diminati' => 'Reguler',
            'status_pendaftaran' => 'pending',
        ]);
        CalonSantri::create([
            'nomor_pendaftaran' => 'PPDB-2026-004',
            'nama' => 'Lulus A',
            'tanggal_lahir' => '2012-04-04',
            'jenis_kelamin' => 'P',
            'no_hp_wali' => '084444444444',
            'program_diminati' => 'Muadalah',
            'status_pendaftaran' => 'lulus',
        ]);

        tenancy()->end();

        tenancy()->initialize($this->tenantB);

        CalonSantri::create([
            'nomor_pendaftaran' => 'PPDB-2026-005',
            'nama' => 'Cadangan B',
            'tanggal_lahir' => '2012-05-05',
            'jenis_kelamin' => 'L',
            'no_hp_wali' => '085555555555',
            'program_diminati' => 'Tahfidz',
            'status_pendaftaran' => 'cadangan',
        ]);
        CalonSantri::create([
            'nomor_pendaftaran' => 'PPDB-2026-006',
            'nama' => 'Ditolak B',
            'tanggal_lahir' => '2012-06-06',
            'jenis_kelamin' => 'P',
            'no_hp_wali' => '086666666666',
            'program_diminati' => 'Reguler',
            'status_pendaftaran' => 'ditolak',
        ]);

        $report = new PpdbReport();
        $counts = $report->getViewData()['counts'];

        $this->assertSame(2, $counts['total']);
        $this->assertSame(0, $counts['lulus']);
        $this->assertSame(1, $counts['cadangan']);
        $this->assertSame(1, $counts['ditolak']);
        $this->assertSame(0, $counts['belum_verifikasi']);

        tenancy()->end();
    }
}
