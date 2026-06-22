<?php

namespace Tests\Feature;

use Modules\Akademik\Filament\Resources\Nilai\NilaiResource;
use Modules\Akademik\Filament\Resources\PeriodeAkademik\PeriodeAkademikResource;
use Modules\Akademik\Filament\Resources\Rapor\RaporResource;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Services\NilaiService;
use Modules\Akademik\Services\RaporService;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class AkademikServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('rapor')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables([
            'rapor',
            'nilai',
            'jadwal_pelajaran',
            'pelajaran',
            'periode_akademik',
            'penempatan_kelas',
            'status_santri',
            'alumni',
            'santri',
            'kelas',
            'settings',
            'role_has_permissions',
            'model_has_roles',
            'model_has_permissions',
            'roles',
            'permissions',
            'users',
            'domains',
            'tenants',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_nilai_service_menolak_nilai_di_luar_rentang(): void
    {
        $tenant = $this->createTestTenant('akad-nilai-range', 'Pesantren Akademik', 'akad-nilai-range');
        tenancy()->initialize($tenant);

        $guru = $this->createGuru('guru-range@test.local');
        [$kelas, $santri, $periode, $pelajaran] = $this->createAkademikFixture($guru);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(NilaiService::class)->simpan([
            'santri_id' => $santri->id,
            'pelajaran_id' => $pelajaran->id,
            'periode_akademik_id' => $periode->id,
            'kelas_id' => $kelas->id,
            'nilai_harian' => 110,
            'nilai_uts' => 80,
            'nilai_uas' => 90,
        ], $guru);
    }

    public function test_rapor_service_menghitung_ipk_dan_ranking_kelas_dengan_benar(): void
    {
        $tenant = $this->createTestTenant('akad-rapor', 'Pesantren Rapor', 'akad-rapor');
        tenancy()->initialize($tenant);

        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'akademik_grade_weights',
            'value' => ['harian' => 20, 'uts' => 30, 'uas' => 50],
        ]);

        $guru = $this->createGuru('guru-rapor@test.local');
        $kelas = Kelas::create([
            'tenant_id' => $tenant->id,
            'nama_kelas' => 'IX-A',
            'tingkat' => '9',
            'capacity' => 30,
            'is_active' => true,
        ]);

        $periode = PeriodeAkademik::create([
            'tenant_id' => $tenant->id,
            'nama' => 'Semester Ganjil 2026/2027',
            'tahun_akademik' => '2026/2027',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_akhir' => '2026-12-31',
            'is_active' => true,
        ]);

        $mat = Pelajaran::create([
            'tenant_id' => $tenant->id,
            'nama' => 'Matematika',
            'kode' => 'MAT-9',
            'sks' => 2,
            'guru_pengampu_id' => $guru->id,
        ]);
        $ipa = Pelajaran::create([
            'tenant_id' => $tenant->id,
            'nama' => 'IPA',
            'kode' => 'IPA-9',
            'sks' => 3,
            'guru_pengampu_id' => $guru->id,
        ]);

        foreach ([$mat, $ipa] as $pelajaran) {
            JadwalPelajaran::create([
                'tenant_id' => $tenant->id,
                'kelas_id' => $kelas->id,
                'pelajaran_id' => $pelajaran->id,
                'periode_akademik_id' => $periode->id,
                'hari' => 'senin',
                'jam_mulai' => '08:00',
                'jam_selesai' => '09:00',
                'ruangan' => 'Ruang 1',
            ]);
        }

        $santriA = Santri::create([
            'tenant_id' => $tenant->id,
            'nis' => 'AKD001',
            'nama' => 'Santri A',
            'tanggal_lahir' => '2011-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'kelas_id' => $kelas->id,
        ]);
        $santriB = Santri::create([
            'tenant_id' => $tenant->id,
            'nis' => 'AKD002',
            'nama' => 'Santri B',
            'tanggal_lahir' => '2011-02-01',
            'jenis_kelamin' => 'P',
            'status' => 'aktif',
            'kelas_id' => $kelas->id,
        ]);

        $nilaiService = app(NilaiService::class);
        $nilaiService->simpan([
            'santri_id' => $santriA->id,
            'pelajaran_id' => $mat->id,
            'periode_akademik_id' => $periode->id,
            'kelas_id' => $kelas->id,
            'nilai_harian' => 90,
            'nilai_uts' => 90,
            'nilai_uas' => 90,
        ], $guru);
        $nilaiService->simpan([
            'santri_id' => $santriA->id,
            'pelajaran_id' => $ipa->id,
            'periode_akademik_id' => $periode->id,
            'kelas_id' => $kelas->id,
            'nilai_harian' => 80,
            'nilai_uts' => 80,
            'nilai_uas' => 80,
        ], $guru);
        $nilaiService->simpan([
            'santri_id' => $santriB->id,
            'pelajaran_id' => $mat->id,
            'periode_akademik_id' => $periode->id,
            'kelas_id' => $kelas->id,
            'nilai_harian' => 70,
            'nilai_uts' => 70,
            'nilai_uas' => 70,
        ], $guru);
        $nilaiService->simpan([
            'santri_id' => $santriB->id,
            'pelajaran_id' => $ipa->id,
            'periode_akademik_id' => $periode->id,
            'kelas_id' => $kelas->id,
            'nilai_harian' => 60,
            'nilai_uts' => 60,
            'nilai_uas' => 60,
        ], $guru);

        $raporList = app(RaporService::class)->generateForKelas($periode, $kelas);

        $this->assertCount(2, $raporList);

        $raporA = $raporList->firstWhere('santri_id', $santriA->id);
        $raporB = $raporList->firstWhere('santri_id', $santriB->id);

        $this->assertSame(1, $raporA->rank_kelas);
        $this->assertSame(2, $raporB->rank_kelas);
        $this->assertGreaterThan($raporB->ipk, $raporA->ipk);
        $this->assertEquals(5.0, $raporA->total_sks);
        $this->assertCount(2, $raporA->ringkasan_nilai);
    }

    public function test_guru_hanya_melihat_nilai_untuk_kelas_dan_pelajaran_yang_diampu(): void
    {
        $tenant = $this->createTestTenant('akad-guru-scope', 'Pesantren Scope Guru', 'akad-guru-scope');
        tenancy()->initialize($tenant);

        $guruA = $this->createGuru('guru-a@test.local');
        $guruB = $this->createGuru('guru-b@test.local');

        $kelasA = Kelas::create([
            'tenant_id' => $tenant->id,
            'nama_kelas' => 'VII-A',
            'tingkat' => '7',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $kelasB = Kelas::create([
            'tenant_id' => $tenant->id,
            'nama_kelas' => 'VII-B',
            'tingkat' => '7',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $periode = PeriodeAkademik::create([
            'tenant_id' => $tenant->id,
            'nama' => 'Aktif',
            'tahun_akademik' => '2026/2027',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_akhir' => '2026-12-31',
            'is_active' => true,
        ]);
        $pelajaranA = Pelajaran::create([
            'tenant_id' => $tenant->id,
            'nama' => 'Bahasa Arab',
            'kode' => 'BAR',
            'sks' => 2,
            'guru_pengampu_id' => $guruA->id,
        ]);
        $pelajaranB = Pelajaran::create([
            'tenant_id' => $tenant->id,
            'nama' => 'Bahasa Inggris',
            'kode' => 'BING',
            'sks' => 2,
            'guru_pengampu_id' => $guruB->id,
        ]);

        JadwalPelajaran::create([
            'tenant_id' => $tenant->id,
            'kelas_id' => $kelasA->id,
            'pelajaran_id' => $pelajaranA->id,
            'periode_akademik_id' => $periode->id,
            'hari' => 'senin',
            'jam_mulai' => '08:00',
            'jam_selesai' => '09:00',
        ]);
        JadwalPelajaran::create([
            'tenant_id' => $tenant->id,
            'kelas_id' => $kelasB->id,
            'pelajaran_id' => $pelajaranB->id,
            'periode_akademik_id' => $periode->id,
            'hari' => 'selasa',
            'jam_mulai' => '08:00',
            'jam_selesai' => '09:00',
        ]);

        $santriA = Santri::create([
            'tenant_id' => $tenant->id,
            'nis' => 'SCP001',
            'nama' => 'Santri Scope A',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'kelas_id' => $kelasA->id,
        ]);
        $santriB = Santri::create([
            'tenant_id' => $tenant->id,
            'nis' => 'SCP002',
            'nama' => 'Santri Scope B',
            'tanggal_lahir' => '2012-02-01',
            'jenis_kelamin' => 'P',
            'status' => 'aktif',
            'kelas_id' => $kelasB->id,
        ]);

        Nilai::create([
            'tenant_id' => $tenant->id,
            'santri_id' => $santriA->id,
            'pelajaran_id' => $pelajaranA->id,
            'periode_akademik_id' => $periode->id,
            'kelas_id' => $kelasA->id,
            'nilai_harian' => 88,
            'nilai_uts' => 87,
            'nilai_uas' => 89,
            'nilai_akhir' => 88,
            'input_by' => $guruA->id,
        ]);
        Nilai::create([
            'tenant_id' => $tenant->id,
            'santri_id' => $santriB->id,
            'pelajaran_id' => $pelajaranB->id,
            'periode_akademik_id' => $periode->id,
            'kelas_id' => $kelasB->id,
            'nilai_harian' => 78,
            'nilai_uts' => 77,
            'nilai_uas' => 79,
            'nilai_akhir' => 78,
            'input_by' => $guruB->id,
        ]);

        $this->actingAs($guruA);

        $records = NilaiResource::getEloquentQuery()->get();

        $this->assertCount(1, $records);
        $this->assertSame($santriA->id, $records->first()->santri_id);
        $this->assertSame($pelajaranA->id, $records->first()->pelajaran_id);
    }

    public function test_route_admin_akademik_terdaftar_dan_resource_bisa_diakses(): void
    {
        $tenant = $this->createTestTenant('akad-route', 'Pesantren Route', 'akad-route');
        tenancy()->initialize($tenant);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Akademik',
            'email' => 'admin-akad@test.local',
            'password' => 'password',
        ]);
        $admin->assignRole('admin_pesantren');

        $this->actingAs($admin);

        $this->assertTrue(PeriodeAkademikResource::canAccess());
        $this->assertTrue(NilaiResource::canAccess());
        $this->assertTrue(RaporResource::canAccess());
        $this->assertTrue(Route::has('tenant.akademik.export.rapor.print'));
        $this->assertTrue(Route::has('filament.admin.resources.akademik.nilai.nilais.index'));
        $this->assertTrue(Route::has('filament.admin.resources.akademik.rapor.rapors.index'));
    }

    private function createGuru(string $email): User
    {
        $user = User::create([
            'name' => 'Guru Akademik',
            'email' => $email,
            'password' => 'password',
        ]);
        $user->assignRole('guru');

        return $user;
    }

    /**
     * @return array{0: Kelas, 1: Santri, 2: PeriodeAkademik, 3: Pelajaran}
     */
    private function createAkademikFixture(User $guru): array
    {
        $kelas = Kelas::create([
            'nama_kelas' => 'VIII-A',
            'tingkat' => '8',
            'capacity' => 30,
            'is_active' => true,
        ]);

        $santri = Santri::create([
            'nis' => 'AKDTEST01',
            'nama' => 'Santri Test',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'kelas_id' => $kelas->id,
        ]);

        $periode = PeriodeAkademik::create([
            'nama' => 'Semester Ganjil',
            'tahun_akademik' => '2026/2027',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_akhir' => '2026-12-31',
            'is_active' => true,
        ]);

        $pelajaran = Pelajaran::create([
            'nama' => 'Fiqih',
            'kode' => 'FIQ-8',
            'sks' => 2,
            'guru_pengampu_id' => $guru->id,
        ]);

        JadwalPelajaran::create([
            'kelas_id' => $kelas->id,
            'pelajaran_id' => $pelajaran->id,
            'periode_akademik_id' => $periode->id,
            'hari' => 'senin',
            'jam_mulai' => '08:00',
            'jam_selesai' => '09:00',
            'ruangan' => 'Ruang A',
        ]);

        return [$kelas, $santri, $periode, $pelajaran];
    }
}
