<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Kegiatan\Models\Kegiatan;
use Modules\Kegiatan\Models\PresensiKegiatan;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Models\Halaqah;
use Modules\Tahfidz\Models\HalaqahSantri;
use Modules\Tahfidz\Models\Munaqasyah;
use Modules\Tahfidz\Models\SimaAn;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-11 — Tahfidz Lanjutan & Kegiatan Khas:
 * (a) gerbang akses_pwa_guru utk layar sima'an/munaqasyah PWA,
 * (b) sima'an idempoten client_ref,
 * (c) munaqasyah 'mengulang' TIDAK masuk daftar cetak wisuda meski ditandai,
 * (d) peserta luar pasaran (JSON) tidak membuat baris santri/presensi_kegiatan.
 */
class Wp11TahfidzLanjutanTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions',
            'model_has_roles', 'model_has_permissions', 'settings',
            'santri', 'halaqah', 'halaqah_santri', 'periode_akademik',
            'sima_an', 'munaqasyah', 'kegiatan', 'presensi_kegiatan',
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

    // ---------------------------------------------------------------
    // (a) Gerbang permission PWA
    // ---------------------------------------------------------------

    public function test_layar_simaan_dan_munaqasyah_digerbang_akses_pwa_guru(): void
    {
        $tenant = $this->bootTenant('wp11ga');
        [$guru] = $this->setupHalaqahDenganSantri();

        Auth::login($guru);

        $this->assertSame(403, $this->hit($tenant, 'GET', '/guru/simaan')->getStatusCode());
        $this->assertSame(403, $this->hit($tenant, 'GET', '/guru/munaqasyah')->getStatusCode());

        $guru->givePermissionTo('akses_pwa_guru');
        Auth::login($guru->fresh());

        $simaan = $this->hit($tenant, 'GET', '/guru/simaan');
        $this->assertSame(200, $simaan->getStatusCode());
        $this->assertStringContainsString('PortalGuru\/Simaan', $simaan->getContent());

        $munaqasyah = $this->hit($tenant, 'GET', '/guru/munaqasyah');
        $this->assertSame(200, $munaqasyah->getStatusCode());
        $this->assertStringContainsString('PortalGuru\/Munaqasyah', $munaqasyah->getContent());
    }

    // ---------------------------------------------------------------
    // (b) Sima'an idempoten via client_ref
    // ---------------------------------------------------------------

    public function test_simaan_client_ref_sama_dikirim_dua_kali_hanya_satu_baris(): void
    {
        $tenant = $this->bootTenant('wp11si');
        [$guru, $santri] = $this->setupHalaqahDenganSantri();
        $guru->givePermissionTo('akses_pwa_guru');
        Auth::login($guru);

        $payload = [
            'santri_id' => $santri->id,
            'rentang' => 'Juz 1-5',
            'nilai_kelancaran' => 90,
            'nilai_tajwid' => 85,
            'catatan' => 'Lancar',
            'tanggal' => now()->toDateString(),
            'client_ref' => (string) Str::uuid(),
        ];

        // Kirim + retry (sinyal jelek) dengan client_ref SAMA.
        $this->assertSame(302, $this->hit($tenant, 'POST', '/guru/simaan', $payload)->getStatusCode());
        $this->assertSame(302, $this->hit($tenant, 'POST', '/guru/simaan', $payload)->getStatusCode());

        $this->assertSame(1, SimaAn::query()->where('santri_id', $santri->id)->count());

        $row = SimaAn::query()->first();
        $this->assertSame('Juz 1-5', $row->rentang);
        $this->assertSame($guru->id, $row->penyimak_id);
        $this->assertSame(90, $row->nilai_kelancaran);
    }

    // ---------------------------------------------------------------
    // (c) Daftar wisuda: 'mengulang' tidak ikut meski ditandai peserta
    // ---------------------------------------------------------------

    public function test_munaqasyah_mengulang_tidak_muncul_di_daftar_wisuda_meski_ditandai(): void
    {
        $tenant = $this->bootTenant('wp11wi');
        [$guru, $santriLulus] = $this->setupHalaqahDenganSantri();
        $santriUlang = $this->createSantri('WP11U2');

        $periode = PeriodeAkademik::create([
            'nama' => 'Ganjil 1447',
            'tahun_akademik' => '2026/2027',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_akhir' => '2026-12-31',
            'is_active' => true,
        ]);

        Munaqasyah::create([
            'santri_id' => $santriLulus->id,
            'juz_diuji' => 'Juz 1-30',
            'penguji_id' => $guru->id,
            'hasil' => 'lulus',
            'nilai' => 92.50,
            'tanggal' => now()->toDateString(),
            'periode_akademik_id' => $periode->id,
            'is_peserta_wisuda' => true,
        ]);

        // MENGULANG tapi keliru ditandai peserta wisuda → TIDAK boleh ikut.
        Munaqasyah::create([
            'santri_id' => $santriUlang->id,
            'juz_diuji' => 'Juz 1-30',
            'penguji_id' => $guru->id,
            'hasil' => 'mengulang',
            'nilai' => 55,
            'tanggal' => now()->toDateString(),
            'periode_akademik_id' => $periode->id,
            'is_peserta_wisuda' => true,
        ]);

        $admin = User::create(['name' => 'Admin TU', 'email' => 'tu-wp11@test.local', 'password' => 'password']);
        $admin->givePermissionTo('view_akademik');
        Auth::login($admin);

        $response = $this->hit($tenant, 'GET', '/admin/tahfidz/export/wisuda/print?periode_akademik_id='.$periode->id);
        $this->assertSame(200, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertStringContainsString($santriLulus->nama, $content);
        $this->assertStringNotContainsString($santriUlang->nama, $content);
    }

    // ---------------------------------------------------------------
    // (d) Peserta luar pasaran: JSON saja, tanpa baris santri/presensi
    // ---------------------------------------------------------------

    public function test_peserta_luar_pasaran_tidak_membuat_baris_santri_atau_presensi(): void
    {
        $this->bootTenant('wp11ps');

        $santriSebelum = Santri::query()->count();

        $kegiatan = Kegiatan::create([
            'nama' => 'Pasaran Kitab Bulughul Maram',
            'jenis' => 'pasaran',
            'mulai' => now(),
            'status' => 'berlangsung',
            'peserta_luar' => [
                ['nama' => 'Ahmad Kalong', 'asal' => 'Kampung Sebelah', 'hadir' => true],
                ['nama' => 'Budi Tamu', 'asal' => 'Pesantren Tetangga', 'hadir' => false],
            ],
        ]);

        $this->assertSame($santriSebelum, Santri::query()->count(), 'Peserta luar tidak boleh menambah baris santri');
        $this->assertSame(0, PresensiKegiatan::query()->where('kegiatan_id', $kegiatan->id)->count(), 'Peserta luar tidak boleh menulis presensi_kegiatan');

        $tersimpan = $kegiatan->fresh()->peserta_luar;
        $this->assertCount(2, $tersimpan);
        $this->assertSame('Ahmad Kalong', $tersimpan[0]['nama']);
        $this->assertTrue($tersimpan[0]['hadir']);
        $this->assertFalse($tersimpan[1]['hadir']);
    }

    // ---------------------------------------------------------------
    // (e) Munaqasyah dari PWA idempoten + tidak bisa set peserta wisuda
    // ---------------------------------------------------------------

    public function test_munaqasyah_pwa_idempoten_dan_tidak_menandai_peserta_wisuda(): void
    {
        $tenant = $this->bootTenant('wp11mq');
        [$guru, $santri] = $this->setupHalaqahDenganSantri();
        $guru->givePermissionTo('akses_pwa_guru');
        Auth::login($guru);

        $payload = [
            'santri_id' => $santri->id,
            'juz_diuji' => 'Juz 30',
            'hasil' => 'lulus',
            'nilai' => 88,
            'tanggal' => now()->toDateString(),
            'client_ref' => (string) Str::uuid(),
            // Upaya menyelundupkan penandaan wisuda dari PWA — harus diabaikan.
            'is_peserta_wisuda' => true,
        ];

        $this->assertSame(302, $this->hit($tenant, 'POST', '/guru/munaqasyah', $payload)->getStatusCode());
        $this->assertSame(302, $this->hit($tenant, 'POST', '/guru/munaqasyah', $payload)->getStatusCode());

        $this->assertSame(1, Munaqasyah::query()->where('santri_id', $santri->id)->count());

        $row = Munaqasyah::query()->first();
        $this->assertSame($guru->id, $row->penguji_id);
        $this->assertFalse($row->is_peserta_wisuda, 'Penandaan wisuda hanya dari panel admin');
    }

    public function test_simaan_santri_di_luar_halaqah_binaan_ditolak(): void
    {
        $tenant = $this->bootTenant('wp11fx');
        [$guru] = $this->setupHalaqahDenganSantri();
        $guru->givePermissionTo('akses_pwa_guru');
        $bukanBinaan = $this->createSantri('WP11X9');
        Auth::login($guru);

        $response = $this->hit($tenant, 'POST', '/guru/simaan', [
            'santri_id' => $bukanBinaan->id,
            'rentang' => 'Juz 1',
            'tanggal' => now()->toDateString(),
            'client_ref' => (string) Str::uuid(),
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(0, SimaAn::query()->count());
    }

    // ---------------------------------------------------------------
    // Helpers (pola PwaGuruF1Test)
    // ---------------------------------------------------------------

    private function bootTenant(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        return $tenant;
    }

    private function hit(Tenant $tenant, string $method, string $uri, array $payload = [])
    {
        $host = $tenant->domains()->first()->domain;
        $request = Request::create("http://{$host}{$uri}", $method, $payload);
        $request->headers->set('Host', $host);

        $response = app(Kernel::class)->handle($request);

        if (! tenancy()->initialized) {
            tenancy()->initialize($tenant);
        }

        return $response;
    }

    /** @return array{0: User, 1: Santri} guru musyrif + santri anggota aktif halaqah-nya */
    private function setupHalaqahDenganSantri(): array
    {
        $guru = User::create([
            'name' => 'Muhaffizh WP11',
            'email' => 'muhaffizh-'.Str::lower(Str::random(6)).'@test.local',
            'password' => 'password',
        ]);

        $halaqah = Halaqah::create([
            'nama' => 'Halaqah WP11',
            'musyrif_id' => $guru->id,
            'jenis' => 'tahfidz',
            'aktif' => true,
        ]);

        $santri = $this->createSantri('WP11S1');

        HalaqahSantri::create([
            'halaqah_id' => $halaqah->id,
            'santri_id' => $santri->id,
            'joined_at' => now()->toDateString(),
        ]);

        return [$guru, $santri];
    }

    private function createSantri(string $nis): Santri
    {
        return Santri::create([
            'nis' => $nis,
            'nama' => 'Santri '.$nis,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'kelas_id' => null,
        ]);
    }
}
