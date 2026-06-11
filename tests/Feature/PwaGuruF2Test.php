<?php

namespace Tests\Feature;

use App\Http\Controllers\PortalGuru\PortalGuruBerandaController;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Modules\Akademik\Models\CapaianKitab;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\JurnalMengajar;
use Modules\Akademik\Models\KajianKitab;
use Modules\Akademik\Models\Kitab;
use Modules\Akademik\Models\KomponenNilai;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\Rapor;
use Modules\Akademik\Services\NilaiGridService;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Models\Halaqah;
use Modules\Tahfidz\Models\HalaqahSantri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F2-PWA — Grid nilai massal (autosave idempoten + validasi server),
 * jurnal KBM (unique guru+jadwal+tanggal), capaian kitab (sorogan vs
 * bandongan), layar wali kelas (isolasi kelas binaan), dan jaminan
 * DUA PINTU SATU JALUR (panel & PWA via NilaiGridService).
 */
class PwaGuruF2Test extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions',
            'model_has_roles', 'model_has_permissions', 'settings',
            'santri', 'kelas', 'pelajaran', 'jadwal_pelajaran', 'periode_akademik',
            'presensi_santri', 'halaqah', 'halaqah_santri',
            'nilai', 'komponen_nilai', 'jurnal_mengajar',
            'kitab', 'kajian_kitab', 'capaian_kitab', 'rapor',
            'jenis_pelanggaran', 'pelanggaran_santri',
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
    // (a) Autosave putus-sambung: baris sama 2× → 1 baris, nilai sama
    // ---------------------------------------------------------------

    public function test_nilai_grid_autosave_kirim_dua_kali_tidak_duplikat(): void
    {
        $tenant = $this->bootTenant('f2nila');

        [$guru, $jadwal, $periode] = $this->setupKbm('Fiqih F2');
        $santri = $this->createSantri('F2N001', $jadwal->kelas_id);
        [$k1, $k2] = $this->seedKomponen();

        Auth::login($guru);

        $payload = ['santri_id' => $santri->id, 'nilai_komponen' => [(string) $k1->id => 80, (string) $k2->id => 90]];
        $url = "/guru/nilai/{$jadwal->kelas_id}/{$jadwal->pelajaran_id}";

        $this->assertSame(200, $this->hit($tenant, 'POST', $url, $payload)->getStatusCode());
        // Retry persis sama (sinyal jelek / putus-sambung).
        $this->assertSame(200, $this->hit($tenant, 'POST', $url, $payload)->getStatusCode());

        $this->assertSame(1, Nilai::where('santri_id', $santri->id)->count(), 'Upsert: retry tidak menggandakan baris');

        $nilai = Nilai::where('santri_id', $santri->id)->firstOrFail();
        $this->assertSame(80.0, (float) $nilai->nilai_komponen[(string) $k1->id]);
        $this->assertSame(90.0, (float) $nilai->nilai_komponen[(string) $k2->id]);
        // Bobot 60/40 → 80*0.6 + 90*0.4 = 84 (rumus NilaiService — TIDAK diubah).
        $this->assertSame(84.0, (float) $nilai->nilai_akhir);
        $this->assertSame($guru->id, $nilai->input_by, 'input_by otomatis dari actor');
    }

    // ---------------------------------------------------------------
    // (b) Validasi server: 0-100 + komponen asing ditolak
    // ---------------------------------------------------------------

    public function test_nilai_di_luar_0_100_dan_komponen_asing_ditolak_server(): void
    {
        $tenant = $this->bootTenant('f2vald');

        [$guru, $jadwal] = $this->setupKbm('Nahwu F2');
        $santri = $this->createSantri('F2V001', $jadwal->kelas_id);
        [$k1] = $this->seedKomponen();

        Auth::login($guru);
        $url = "/guru/nilai/{$jadwal->kelas_id}/{$jadwal->pelajaran_id}";

        // Nilai 101 dan -1 → 0 baris tertulis.
        foreach ([101, -1] as $liar) {
            $this->hit($tenant, 'POST', $url, [
                'santri_id' => $santri->id,
                'nilai_komponen' => [(string) $k1->id => $liar],
            ]);
            $this->assertSame(0, Nilai::count(), "Nilai {$liar} harus ditolak server");
        }

        // Komponen asing (id liar) → ditolak NilaiService guard F0.
        $this->hit($tenant, 'POST', $url, [
            'santri_id' => $santri->id,
            'nilai_komponen' => ['999999' => 80],
        ]);
        $this->assertSame(0, Nilai::count(), 'Komponen asing harus ditolak');

        // Guru lain (bukan pengampu) → 403 tanpa menulis.
        $penyusup = $this->createGuru('penyusup-nilai@test.local');
        $penyusup->assignRole('guru');
        Auth::login($penyusup->fresh());

        $status = $this->hit($tenant, 'POST', $url, [
            'santri_id' => $santri->id,
            'nilai_komponen' => [(string) $k1->id => 80],
        ])->getStatusCode();
        $this->assertSame(403, $status);
        $this->assertSame(0, Nilai::count());
    }

    // ---------------------------------------------------------------
    // (c) Jurnal KBM: unique guru+jadwal+tanggal — 2× simpan = 1 baris
    // ---------------------------------------------------------------

    public function test_jurnal_kbm_dua_kali_simpan_satu_baris_terupdate(): void
    {
        $tenant = $this->bootTenant('f2jurn');

        [$guru, $jadwal] = $this->setupKbm('Shorof F2');
        $santri = $this->createSantri('F2J001', $jadwal->kelas_id);

        Auth::login($guru);

        // Presensi dulu (jumlah_hadir dihitung server dari presensi tersimpan).
        $this->hit($tenant, 'POST', "/guru/presensi/{$jadwal->id}", [
            'presensi' => [['santri_id' => $santri->id, 'status' => 'hadir']],
        ]);

        $url = "/guru/jurnal/{$jadwal->id}";
        $this->assertSame(302, $this->hit($tenant, 'POST', $url, [
            'materi' => 'Bab Fi\'il Madhi',
            'status' => 'terlaksana',
        ])->getStatusCode());

        // Simpan ulang dengan materi direvisi → baris yang SAMA terupdate.
        $this->hit($tenant, 'POST', $url, [
            'materi' => 'Bab Fi\'il Madhi (revisi)',
            'metode' => 'hafalan',
            'status' => 'terlaksana',
        ]);

        $this->assertSame(1, JurnalMengajar::count(), 'Unique guru+jadwal+tanggal: 2× simpan = 1 baris');
        $jurnal = JurnalMengajar::firstOrFail();
        $this->assertSame('Bab Fi\'il Madhi (revisi)', $jurnal->materi);
        $this->assertSame('hafalan', $jurnal->metode);
        $this->assertSame(1, $jurnal->jumlah_hadir, 'jumlah_hadir auto-count dari presensi');

        // Status terlaksana tanpa materi → ditolak.
        $jadwal2 = $this->createJadwalHariIni($guru, 'Tarikh F2');
        $this->hit($tenant, 'POST', "/guru/jurnal/{$jadwal2->id}", ['status' => 'terlaksana']);
        $this->assertSame(1, JurnalMengajar::count(), 'Terlaksana tanpa materi harus ditolak');

        // Guru lain tidak boleh menulis jurnal sesi ini.
        $lain = $this->createGuru('guru-lain-jurnal@test.local');
        $lain->givePermissionTo('akses_pwa_guru');
        Auth::login($lain);
        $this->assertSame(403, $this->hit($tenant, 'POST', $url, [
            'materi' => 'Sabotase',
            'status' => 'terlaksana',
        ])->getStatusCode());
    }

    // ---------------------------------------------------------------
    // (d) Sorogan vs bandongan
    // ---------------------------------------------------------------

    public function test_sorogan_per_santri_bandongan_satu_baris_kelompok(): void
    {
        $tenant = $this->bootTenant('f2ktab');

        $guru = $this->createGuru('pengampu-kitab@test.local');
        $guru->givePermissionTo('akses_pwa_guru');

        $halaqah = Halaqah::create(['nama' => 'Halaqah Kitab', 'musyrif_id' => $guru->id, 'jenis' => 'kitab', 'aktif' => true]);
        $s1 = $this->createSantri('F2K001', null);
        $s2 = $this->createSantri('F2K002', null);
        foreach ([$s1, $s2] as $s) {
            HalaqahSantri::create(['halaqah_id' => $halaqah->id, 'santri_id' => $s->id, 'joined_at' => now()->toDateString()]);
        }

        $kitab = Kitab::create(['nama' => 'Safinah']);
        $sorogan = KajianKitab::create(['kitab_id' => $kitab->id, 'pengampu_id' => $guru->id, 'halaqah_id' => $halaqah->id, 'metode' => 'sorogan', 'aktif' => true]);
        $bandongan = KajianKitab::create(['kitab_id' => $kitab->id, 'pengampu_id' => $guru->id, 'halaqah_id' => $halaqah->id, 'metode' => 'bandongan', 'aktif' => true]);

        Auth::login($guru);

        // Sorogan: hanya 1 santri tertulis.
        $this->hit($tenant, 'POST', "/guru/kitab/{$sorogan->id}", [
            'santri_id' => $s1->id,
            'posisi' => 'Bab Thaharah hal. 12',
            'status' => 'lanjut',
        ]);
        $this->assertSame(1, CapaianKitab::where('kajian_kitab_id', $sorogan->id)->count());
        $this->assertSame($s1->id, CapaianKitab::where('kajian_kitab_id', $sorogan->id)->first()->santri_id, 'Sorogan hanya untuk 1 santri itu');
        $this->assertSame(0, CapaianKitab::where('kajian_kitab_id', $sorogan->id)->where('santri_id', $s2->id)->count());

        // Sorogan tanpa santri → ditolak.
        $this->hit($tenant, 'POST', "/guru/kitab/{$sorogan->id}", ['posisi' => 'hal. 13', 'status' => 'lanjut']);
        $this->assertSame(1, CapaianKitab::where('kajian_kitab_id', $sorogan->id)->count());

        // Sorogan santri di luar peserta → ditolak.
        $luar = $this->createSantri('F2K999', null);
        $this->hit($tenant, 'POST', "/guru/kitab/{$sorogan->id}", [
            'santri_id' => $luar->id, 'posisi' => 'hal. 14', 'status' => 'lanjut',
        ]);
        $this->assertSame(1, CapaianKitab::where('kajian_kitab_id', $sorogan->id)->count());

        // Bandongan: SATU baris kelompok (santri_id NULL) berlaku semua peserta.
        $this->hit($tenant, 'POST', "/guru/kitab/{$bandongan->id}", [
            'posisi' => 'Bab Shalat hal. 30',
            'status' => 'lanjut',
        ]);
        $rows = CapaianKitab::where('kajian_kitab_id', $bandongan->id)->get();
        $this->assertCount(1, $rows, 'Bandongan = 1 baris kelompok, bukan per santri');
        $this->assertNull($rows->first()->santri_id, 'santri_id NULL = posisi kelompok');

        // Bandongan dengan santri_id → ditolak (salah pintu).
        $this->hit($tenant, 'POST', "/guru/kitab/{$bandongan->id}", [
            'santri_id' => $s1->id, 'posisi' => 'hal. 31', 'status' => 'lanjut',
        ]);
        $this->assertSame(1, CapaianKitab::where('kajian_kitab_id', $bandongan->id)->count());

        // Pengampu lain → 403.
        $lain = $this->createGuru('bukan-pengampu-kitab@test.local');
        $lain->givePermissionTo('akses_pwa_guru');
        Auth::login($lain);
        $this->assertSame(403, $this->hit($tenant, 'GET', "/guru/kitab/{$sorogan->id}")->getStatusCode());
        $this->assertSame(403, $this->hit($tenant, 'POST', "/guru/kitab/{$sorogan->id}", [
            'santri_id' => $s1->id, 'posisi' => 'x', 'status' => 'lanjut',
        ])->getStatusCode());
    }

    // ---------------------------------------------------------------
    // (e) Wali kelas hanya lihat/menulis kelas binaannya
    // ---------------------------------------------------------------

    public function test_wali_kelas_hanya_kelas_binaannya_guru_lain_403(): void
    {
        $tenant = $this->bootTenant('f2wali');

        $wali = $this->createGuru('wali-kelas@test.local');
        $wali->givePermissionTo('akses_pwa_guru');
        $bukanWali = $this->createGuru('bukan-wali@test.local');
        $bukanWali->givePermissionTo('akses_pwa_guru');

        $periode = $this->createPeriodeAktif();
        $kelas = Kelas::create(['nama_kelas' => 'VIII-W', 'tingkat' => '8', 'capacity' => 30, 'is_active' => true, 'wali_kelas_id' => $wali->id]);
        $santri = $this->createSantri('F2W001', $kelas->id);

        Auth::login($wali);

        $this->assertSame(200, $this->hit($tenant, 'GET', "/guru/wali-kelas/{$kelas->id}")->getStatusCode());

        // Catatan wali kelas → tersimpan di rapor.catatan_walikelas (upsert).
        $this->hit($tenant, 'POST', "/guru/wali-kelas/{$kelas->id}/catatan", [
            'santri_id' => $santri->id,
            'catatan' => 'Perlu motivasi belajar.',
        ]);
        $this->hit($tenant, 'POST', "/guru/wali-kelas/{$kelas->id}/catatan", [
            'santri_id' => $santri->id,
            'catatan' => 'Perlu motivasi belajar (revisi).',
        ]);

        $this->assertSame(1, Rapor::where('santri_id', $santri->id)->count(), 'Catatan upsert per santri+periode');
        $this->assertSame(
            'Perlu motivasi belajar (revisi).',
            Rapor::where('santri_id', $santri->id)->where('periode_akademik_id', $periode->id)->first()->catatan_walikelas,
        );

        // Guru lain → 403 untuk lihat & tulis, tanpa menulis apa pun.
        Auth::login($bukanWali);
        $this->assertSame(403, $this->hit($tenant, 'GET', "/guru/wali-kelas/{$kelas->id}")->getStatusCode());
        $this->assertSame(403, $this->hit($tenant, 'POST', "/guru/wali-kelas/{$kelas->id}/catatan", [
            'santri_id' => $santri->id,
            'catatan' => 'Sabotase catatan.',
        ])->getStatusCode());
        $this->assertSame(1, Rapor::count());
        $this->assertStringContainsString('revisi', Rapor::first()->catatan_walikelas);
    }

    // ---------------------------------------------------------------
    // (f) DUA PINTU SATU JALUR: panel (service) & PWA (HTTP) identik
    // ---------------------------------------------------------------

    public function test_panel_dan_pwa_menulis_lewat_jalur_yang_sama(): void
    {
        $tenant = $this->bootTenant('f2dual');

        [$guru, $jadwal, $periode] = $this->setupKbm('Balaghah F2');
        $santri = $this->createSantri('F2D001', $jadwal->kelas_id);
        [$k1, $k2] = $this->seedKomponen();

        Auth::login($guru);

        $komponenPayload = [(string) $k1->id => 70, (string) $k2->id => 95];

        // Pintu 1 — PWA (HTTP autosave per baris).
        $this->hit($tenant, 'POST', "/guru/nilai/{$jadwal->kelas_id}/{$jadwal->pelajaran_id}", [
            'santri_id' => $santri->id,
            'nilai_komponen' => $komponenPayload,
        ]);

        $viaPwa = Nilai::where('santri_id', $santri->id)->firstOrFail();
        $snapshot = [
            'nilai_komponen' => $viaPwa->nilai_komponen,
            'nilai_akhir' => (float) $viaPwa->nilai_akhir,
            'input_by' => $viaPwa->input_by,
        ];

        // Pintu 2 — panel (loop simpan massal memanggil service yang sama).
        $viaPanel = app(NilaiGridService::class)->simpanBaris([
            'santri_id' => $santri->id,
            'pelajaran_id' => $jadwal->pelajaran_id,
            'periode_akademik_id' => $periode->id,
            'kelas_id' => $jadwal->kelas_id,
            'nilai_komponen' => $komponenPayload,
        ], $guru);

        // Baris IDENTIK: tetap 1 baris, id sama, isi sama.
        $this->assertSame(1, Nilai::where('santri_id', $santri->id)->count(), 'Dua pintu tidak boleh membuat 2 baris');
        $this->assertSame($viaPwa->id, $viaPanel->id);
        $this->assertSame($snapshot['nilai_komponen'], $viaPanel->nilai_komponen);
        $this->assertSame($snapshot['nilai_akhir'], (float) $viaPanel->nilai_akhir);
        $this->assertSame($snapshot['input_by'], $viaPanel->input_by);
        // 70*0.6 + 95*0.4 = 80 — rumus tunggal NilaiService.
        $this->assertSame(80.0, (float) $viaPanel->nilai_akhir);
    }

    // ---------------------------------------------------------------
    // Helpers
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

    private function createGuru(string $email): User
    {
        return User::create([
            'name' => 'Guru '.$email,
            'email' => $email,
            'password' => 'password',
        ]);
    }

    private function createSantri(string $nis, ?int $kelasId): Santri
    {
        return Santri::create([
            'nis' => $nis,
            'nama' => 'Santri '.$nis,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'kelas_id' => $kelasId,
        ]);
    }

    private function createPeriodeAktif(): PeriodeAkademik
    {
        return PeriodeAkademik::firstOrCreate(
            ['nama' => 'Semester Uji F2'],
            [
                'tahun_akademik' => '2026/2027',
                'semester' => 'ganjil',
                'tanggal_mulai' => now()->subMonth()->toDateString(),
                'tanggal_akhir' => now()->addMonths(5)->toDateString(),
                'is_active' => true,
            ],
        );
    }

    /** @return array{0: User, 1: JadwalPelajaran, 2: PeriodeAkademik} */
    private function setupKbm(string $namaPelajaran): array
    {
        $guru = $this->createGuru(str()->slug($namaPelajaran).'@test.local');
        $guru->assignRole('guru'); // role guru: akses_pwa_guru + input nilai

        $jadwal = $this->createJadwalHariIni($guru, $namaPelajaran);

        return [$guru->fresh(), $jadwal, PeriodeAkademik::where('is_active', true)->firstOrFail()];
    }

    private function createJadwalHariIni(User $guru, string $namaPelajaran): JadwalPelajaran
    {
        $kelas = Kelas::create([
            'nama_kelas' => 'VII-'.strtoupper(substr(md5($namaPelajaran), 0, 3)),
            'tingkat' => '7',
            'capacity' => 30,
            'is_active' => true,
        ]);

        $periode = $this->createPeriodeAktif();

        $pelajaran = Pelajaran::create([
            'nama' => $namaPelajaran,
            'kode' => strtoupper(substr(md5($namaPelajaran), 0, 6)),
            'sks' => 2,
            'guru_pengampu_id' => $guru->id,
        ]);

        return JadwalPelajaran::create([
            'kelas_id' => $kelas->id,
            'pelajaran_id' => $pelajaran->id,
            'periode_akademik_id' => $periode->id,
            'hari' => PortalGuruBerandaController::hariIni(),
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:30',
        ]);
    }

    /** Komponen default tenant: Tugas 60% + Ujian 40%. @return array{0: KomponenNilai, 1: KomponenNilai} */
    private function seedKomponen(): array
    {
        return [
            KomponenNilai::create(['nama' => 'Tugas', 'bobot' => 60, 'urutan' => 1]),
            KomponenNilai::create(['nama' => 'Ujian', 'bobot' => 40, 'urutan' => 2]),
        ];
    }
}
