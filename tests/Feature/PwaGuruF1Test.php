<?php

namespace Tests\Feature;

use App\Http\Controllers\PortalGuru\PortalGuruBerandaController;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\PresensiSantri;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Models\HafalanTahfidz;
use Modules\Tahfidz\Models\Halaqah;
use Modules\Tahfidz\Models\HalaqahSantri;
use Modules\Tahfidz\Models\PenilaianTahfidz;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F1-PWA — PWA Guru inti: gerbang permission akses_pwa_guru, layar Hari Ini,
 * presensi kelas idempoten (retry tidak dobel + penjagaan rombel), setoran
 * tahfidz idempoten client_ref, isolasi halaqah per musyrif, manifest branding.
 */
class PwaGuruF1Test extends TestCase
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
            'tahfidz_hafalan', 'tahfidz_penilaian',
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
    // (a) Gerbang permission + beranda berisi sesi hari ini
    // ---------------------------------------------------------------

    public function test_tanpa_permission_403_dengan_permission_beranda_berisi_sesi_hari_ini(): void
    {
        $tenant = $this->bootTenant('pwagrb');

        $guru = $this->createGuru('guru-beranda@test.local');
        $jadwal = $this->createJadwalHariIni($guru, 'Fiqih Kontemporer');

        Auth::login($guru);

        // Tanpa permission → 403.
        $this->assertSame(403, $this->hit($tenant, 'GET', '/guru')->getStatusCode());

        // Role guru (dapat akses_pwa_guru dari seeder) → 200 + sesi hari ini.
        $guru->assignRole('guru');
        Auth::login($guru->fresh());

        $response = $this->hit($tenant, 'GET', '/guru');
        $this->assertSame(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Fiqih Kontemporer', $content);
        $this->assertStringContainsString((string) $jadwal->kelas->nama_kelas, $content);
        $this->assertStringContainsString('PortalGuru\/Beranda', $content);
    }

    public function test_beranda_tanpa_jadwal_tetap_200_empty_state(): void
    {
        $tenant = $this->bootTenant('pwagre');

        $guru = $this->createGuru('guru-kosong@test.local');
        $guru->givePermissionTo('akses_pwa_guru');
        Auth::login($guru);

        $response = $this->hit($tenant, 'GET', '/guru');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('"sesi":[]', $response->getContent());
    }

    // ---------------------------------------------------------------
    // (b) Presensi retry → 1 baris per santri, status terakhir menang
    // ---------------------------------------------------------------

    public function test_presensi_kirim_dua_kali_satu_baris_per_santri_status_terakhir_menang(): void
    {
        $tenant = $this->bootTenant('pwagrp');

        $guru = $this->createGuru('guru-presensi@test.local');
        $guru->givePermissionTo('akses_pwa_guru');
        $jadwal = $this->createJadwalHariIni($guru, 'Nahwu');

        $s1 = $this->createSantri('PRS001', $jadwal->kelas_id);
        $s2 = $this->createSantri('PRS002', $jadwal->kelas_id);

        Auth::login($guru);

        $tanggal = now()->toDateString();
        $payload = fn (string $statusS2) => [
            'tanggal' => $tanggal,
            'presensi' => [
                ['santri_id' => $s1->id, 'status' => 'hadir'],
                ['santri_id' => $s2->id, 'status' => $statusS2],
            ],
        ];

        // Kirim pertama + retry (sinyal jelek) dengan status berubah.
        $this->assertSame(302, $this->hit($tenant, 'POST', "/guru/presensi/{$jadwal->id}", $payload('hadir'))->getStatusCode());
        $this->assertSame(302, $this->hit($tenant, 'POST', "/guru/presensi/{$jadwal->id}", $payload('sakit'))->getStatusCode());

        $this->assertSame(1, PresensiSantri::where('santri_id', $s1->id)->whereDate('tanggal', $tanggal)->count());
        $this->assertSame(1, PresensiSantri::where('santri_id', $s2->id)->whereDate('tanggal', $tanggal)->count());
        $this->assertSame('hadir', PresensiSantri::where('santri_id', $s1->id)->first()->status);
        $this->assertSame('sakit', PresensiSantri::where('santri_id', $s2->id)->first()->status, 'Status terakhir harus menang');
        $this->assertSame($guru->id, PresensiSantri::where('santri_id', $s1->id)->first()->dicatat_oleh);
    }

    // ---------------------------------------------------------------
    // (c) Presensi santri kelas lain ditolak
    // ---------------------------------------------------------------

    public function test_presensi_santri_kelas_lain_ditolak_dan_tidak_menulis_apa_pun(): void
    {
        $tenant = $this->bootTenant('pwagrx');

        $guru = $this->createGuru('guru-lintas@test.local');
        $guru->givePermissionTo('akses_pwa_guru');
        $jadwal = $this->createJadwalHariIni($guru, 'Shorof');

        $anggota = $this->createSantri('LNT001', $jadwal->kelas_id);
        $kelasLain = Kelas::create(['nama_kelas' => 'IX-Z', 'tingkat' => '9', 'capacity' => 30, 'is_active' => true]);
        $penyusup = $this->createSantri('LNT002', $kelasLain->id);

        Auth::login($guru);

        $response = $this->hit($tenant, 'POST', "/guru/presensi/{$jadwal->id}", [
            'tanggal' => now()->toDateString(),
            'presensi' => [
                ['santri_id' => $anggota->id, 'status' => 'hadir'],
                ['santri_id' => $penyusup->id, 'status' => 'hadir'],
            ],
        ]);

        // ValidationException → redirect-with-errors; yang penting: TIDAK ada
        // baris presensi tertulis (validasi rombel sebelum transaksi).
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame(0, PresensiSantri::count(), 'Payload berisi santri kelas lain harus ditolak utuh');

        // Guru lain (bukan pengampu) tidak boleh menulis sesi ini sama sekali.
        $bukanPengampu = $this->createGuru('bukan-pengampu@test.local');
        $bukanPengampu->givePermissionTo('akses_pwa_guru');
        Auth::login($bukanPengampu);

        $status = $this->hit($tenant, 'POST', "/guru/presensi/{$jadwal->id}", [
            'presensi' => [['santri_id' => $anggota->id, 'status' => 'alpa']],
        ])->getStatusCode();
        $this->assertSame(403, $status);
        $this->assertSame(0, PresensiSantri::count());
    }

    // ---------------------------------------------------------------
    // (d) Setoran dobel client_ref → 1 hafalan
    // ---------------------------------------------------------------

    public function test_setoran_dobel_client_ref_hanya_satu_hafalan(): void
    {
        $tenant = $this->bootTenant('pwagrs');

        $musyrif = $this->createGuru('musyrif-setor@test.local');
        $musyrif->givePermissionTo('akses_pwa_guru');
        $halaqah = $this->createHalaqahDenganAnggota($musyrif, 'Halaqah Setor', ['STR001']);
        $santri = Santri::where('nis', 'STR001')->firstOrFail();

        Auth::login($musyrif);

        $payload = [
            'santri_id' => $santri->id,
            'halaqah_id' => $halaqah->id,
            'surah' => 'Al-Mulk',
            'ayat_awal' => 1,
            'ayat_akhir' => 10,
            'jenis' => 'ziyadah',
            'nilai' => 90,
            'catatan' => 'Lancar',
            'client_ref' => (string) Str::uuid(),
        ];

        $this->assertSame(302, $this->hit($tenant, 'POST', '/guru/setoran', $payload)->getStatusCode());
        // Retry persis sama (sinyal jelek).
        $this->assertSame(302, $this->hit($tenant, 'POST', '/guru/setoran', $payload)->getStatusCode());

        $this->assertSame(1, HafalanTahfidz::where('santri_id', $santri->id)->count(), 'client_ref sama = 1 hafalan');
        $this->assertSame(1, PenilaianTahfidz::where('santri_id', $santri->id)->count(), 'Penilaian juga tidak dobel');

        $hafalan = HafalanTahfidz::where('santri_id', $santri->id)->firstOrFail();
        $this->assertSame('selesai', $hafalan->status);
        $this->assertSame($halaqah->id, $hafalan->halaqah_id);

        $penilaian = PenilaianTahfidz::where('santri_id', $santri->id)->firstOrFail();
        $this->assertSame('ziyadah', $penilaian->jenis);
        $this->assertSame($musyrif->id, $penilaian->input_by);

        // client_ref baru = entri baru yang sah.
        $payload['client_ref'] = (string) Str::uuid();
        $this->hit($tenant, 'POST', '/guru/setoran', $payload);
        $this->assertSame(2, HafalanTahfidz::where('santri_id', $santri->id)->count());
    }

    // ---------------------------------------------------------------
    // (e) Guru hanya melihat halaqah miliknya
    // ---------------------------------------------------------------

    public function test_guru_hanya_melihat_dan_menulis_halaqah_miliknya(): void
    {
        $tenant = $this->bootTenant('pwagrh');

        $musyrifA = $this->createGuru('musyrif-a@test.local');
        $musyrifA->givePermissionTo('akses_pwa_guru');
        $musyrifB = $this->createGuru('musyrif-b@test.local');

        $halaqahA = $this->createHalaqahDenganAnggota($musyrifA, 'Halaqah Milik A', ['HQA001']);
        $halaqahB = $this->createHalaqahDenganAnggota($musyrifB, 'Halaqah Milik B', ['HQB001']);

        Auth::login($musyrifA);

        $index = $this->hit($tenant, 'GET', '/guru/setoran');
        $this->assertSame(200, $index->getStatusCode());
        $this->assertStringContainsString('Halaqah Milik A', $index->getContent());
        $this->assertStringNotContainsString('Halaqah Milik B', $index->getContent());

        // Membuka form halaqah orang lain → 403.
        $this->assertSame(403, $this->hit($tenant, 'GET', "/guru/setoran/{$halaqahB->id}")->getStatusCode());

        // Menyetorkan ke halaqah orang lain → 403, tidak ada hafalan tertulis.
        $santriB = Santri::where('nis', 'HQB001')->firstOrFail();
        $status = $this->hit($tenant, 'POST', '/guru/setoran', [
            'santri_id' => $santriB->id,
            'halaqah_id' => $halaqahB->id,
            'surah' => 'An-Naba',
            'jenis' => 'murajaah',
            'client_ref' => (string) Str::uuid(),
        ])->getStatusCode();
        $this->assertSame(403, $status);
        $this->assertSame(0, HafalanTahfidz::count());
    }

    public function test_setoran_santri_di_luar_anggota_halaqah_ditolak(): void
    {
        $tenant = $this->bootTenant('pwagrn');

        $musyrif = $this->createGuru('musyrif-n@test.local');
        $musyrif->givePermissionTo('akses_pwa_guru');
        $halaqah = $this->createHalaqahDenganAnggota($musyrif, 'Halaqah N', ['HQN001']);
        $luar = $this->createSantri('HQN999', null);

        Auth::login($musyrif);

        $status = $this->hit($tenant, 'POST', '/guru/setoran', [
            'santri_id' => $luar->id,
            'halaqah_id' => $halaqah->id,
            'surah' => 'Yasin',
            'jenis' => 'ziyadah',
            'client_ref' => (string) Str::uuid(),
        ])->getStatusCode();

        $this->assertSame(422, $status);
        $this->assertSame(0, HafalanTahfidz::count());
    }

    // ---------------------------------------------------------------
    // (f) Manifest /guru/manifest.json berisi branding tenant
    // ---------------------------------------------------------------

    public function test_manifest_guru_berisi_branding_tenant(): void
    {
        $tenant = $this->bootTenant('pwagrm');

        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'branding_name',
            'value' => ['text' => 'Pondok Guru Digital'],
        ]);
        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'branding_primary_color',
            'value' => ['color' => '#14532d'],
        ]);

        $response = $this->hit($tenant, 'GET', '/guru/manifest.json');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/manifest+json', (string) $response->headers->get('content-type'));

        $manifest = json_decode((string) $response->getContent(), true);
        $this->assertSame('Pondok Guru Digital · PWA Guru', $manifest['name']);
        $this->assertSame('/guru', $manifest['start_url']);
        $this->assertSame('/guru/', $manifest['scope']);
        $this->assertSame('#14532d', $manifest['theme_color']);
        $this->assertCount(2, $manifest['icons']);
        $this->assertStringContainsString('/guru/icon.svg?size=192', $manifest['icons'][0]['src']);

        // SW ber-scope /guru/ + cache shell.
        $sw = $this->hit($tenant, 'GET', '/guru/sw.js');
        $this->assertSame(200, $sw->getStatusCode());
        $this->assertSame('/guru/', $sw->headers->get('Service-Worker-Allowed'));
        $this->assertStringContainsString('guru-shell-', (string) $sw->getContent());
    }

    // ---------------------------------------------------------------
    // (g) Adversarial: role non-pegawai, tanggal liar, payload setoran liar,
    //     santri keluar halaqah, SW tidak meng-cache halaman ber-auth
    // ---------------------------------------------------------------

    public function test_role_santri_wali_peserta_tidak_bisa_masuk_pwa_guru(): void
    {
        $tenant = $this->bootTenant('pwagrz');

        foreach (['santri', 'wali', 'peserta'] as $role) {
            $user = $this->createGuru($role.'-bukan-guru@test.local');
            $user->assignRole($role);
            Auth::login($user->fresh());

            $this->assertSame(
                403,
                $this->hit($tenant, 'GET', '/guru')->getStatusCode(),
                "Role {$role} tidak boleh masuk /guru",
            );
        }

        // Anon → bukan 200 (redirect login / 401), bukan bocor halaman.
        Auth::logout();
        $anon = $this->hit($tenant, 'GET', '/guru')->getStatusCode();
        $this->assertContains($anon, [302, 401], 'Anon tidak boleh melihat /guru');
    }

    public function test_presensi_tanggal_masa_depan_dan_terlalu_lampau_ditolak(): void
    {
        $tenant = $this->bootTenant('pwagrt');

        $guru = $this->createGuru('guru-tanggal@test.local');
        $guru->givePermissionTo('akses_pwa_guru');
        $jadwal = $this->createJadwalHariIni($guru, 'Tarikh');
        $santri = $this->createSantri('TGL001', $jadwal->kelas_id);

        Auth::login($guru);

        foreach ([now()->addDay()->toDateString(), now()->subDays(8)->toDateString()] as $tanggalLiar) {
            $this->hit($tenant, 'POST', "/guru/presensi/{$jadwal->id}", [
                'tanggal' => $tanggalLiar,
                'presensi' => [['santri_id' => $santri->id, 'status' => 'hadir']],
            ]);

            $this->assertSame(
                0,
                PresensiSantri::count(),
                "Tanggal {$tanggalLiar} di luar jendela harus ditolak",
            );
        }

        // Kemarin (dalam jendela susulan 7 hari) tetap sah.
        $this->hit($tenant, 'POST', "/guru/presensi/{$jadwal->id}", [
            'tanggal' => now()->subDay()->toDateString(),
            'presensi' => [['santri_id' => $santri->id, 'status' => 'izin']],
        ]);
        $this->assertSame(1, PresensiSantri::count());
    }

    public function test_setoran_payload_liar_ditolak_tanpa_menulis_hafalan(): void
    {
        $tenant = $this->bootTenant('pwagrl');

        $musyrif = $this->createGuru('musyrif-liar@test.local');
        $musyrif->givePermissionTo('akses_pwa_guru');
        $halaqah = $this->createHalaqahDenganAnggota($musyrif, 'Halaqah Liar', ['LIA001']);
        $santri = Santri::where('nis', 'LIA001')->firstOrFail();

        Auth::login($musyrif);

        $dasar = [
            'santri_id' => $santri->id,
            'halaqah_id' => $halaqah->id,
            'surah' => 'Al-Baqarah',
            'jenis' => 'ziyadah',
        ];

        $kasus = [
            'ayat terbalik' => ['ayat_awal' => 10, 'ayat_akhir' => 5],
            'ayat_akhir tanpa ayat_awal' => ['ayat_akhir' => 5],
            'ayat di luar 1-286' => ['ayat_awal' => 0, 'ayat_akhir' => 287],
            'nilai > 100' => ['nilai' => 101],
            'nilai < 0' => ['nilai' => -1],
            'client_ref string kosong' => ['client_ref' => ''],
            'client_ref bukan uuid' => ['client_ref' => 'bukan-uuid'],
        ];

        foreach ($kasus as $label => $override) {
            $payload = array_merge($dasar, ['client_ref' => (string) Str::uuid()], $override);
            $this->hit($tenant, 'POST', '/guru/setoran', $payload);
            $this->assertSame(0, HafalanTahfidz::count(), "Kasus '{$label}' harus ditolak");
        }
    }

    public function test_setoran_santri_sudah_keluar_halaqah_ditolak(): void
    {
        $tenant = $this->bootTenant('pwagrk');

        $musyrif = $this->createGuru('musyrif-keluar@test.local');
        $musyrif->givePermissionTo('akses_pwa_guru');
        $halaqah = $this->createHalaqahDenganAnggota($musyrif, 'Halaqah Keluar', ['KLR001']);
        $santri = Santri::where('nis', 'KLR001')->firstOrFail();

        // Santri sudah keluar halaqah (left_at terisi).
        HalaqahSantri::where('halaqah_id', $halaqah->id)
            ->where('santri_id', $santri->id)
            ->update(['left_at' => now()->subDay()->toDateString()]);

        Auth::login($musyrif);

        $status = $this->hit($tenant, 'POST', '/guru/setoran', [
            'santri_id' => $santri->id,
            'halaqah_id' => $halaqah->id,
            'surah' => 'An-Nas',
            'jenis' => 'murajaah',
            'client_ref' => (string) Str::uuid(),
        ])->getStatusCode();

        $this->assertSame(422, $status, 'Santri yang sudah keluar halaqah tidak boleh disetorkan');
        $this->assertSame(0, HafalanTahfidz::count());
    }

    public function test_sw_hanya_meng_cache_shell_publik_bukan_halaman_ber_auth(): void
    {
        $tenant = $this->bootTenant('pwagrw');

        $sw = (string) $this->hit($tenant, 'GET', '/guru/sw.js')->getContent();

        // Gerbang cache eksplisit: hanya URL yang terdaftar di SHELL_URLS.
        $this->assertStringContainsString('SHELL_URLS.includes', $sw);
        // Tidak ada jalur yang meng-cache semua halaman /guru/* (data santri).
        $this->assertStringNotContainsString('isGuruPage', $sw);
        $this->assertStringNotContainsString("startsWith('/guru/')", $sw);
        // Shell hanya aset publik tanpa data — periksa isi SHELL_URLS itu sendiri.
        $this->assertSame(1, preg_match('/const SHELL_URLS = (\[[^\]]*\]);/', $sw, $m));
        $shellUrls = json_decode($m[1], true);
        $this->assertSame(
            ['/guru/manifest.json', '/guru/icon.svg?size=192', '/guru/icon.svg?size=512'],
            $shellUrls,
            'SHELL_URLS hanya boleh berisi aset publik, tanpa halaman ber-auth',
        );
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

        // Kernel handle mengakhiri tenancy pada terminasi tertentu — pastikan
        // assertion DB berikutnya tetap pada koneksi tenant yang benar.
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

    private function createJadwalHariIni(User $guru, string $namaPelajaran): JadwalPelajaran
    {
        $kelas = Kelas::create([
            'nama_kelas' => 'VII-'.strtoupper(substr(md5($namaPelajaran), 0, 3)),
            'tingkat' => '7',
            'capacity' => 30,
            'is_active' => true,
        ]);

        $periode = PeriodeAkademik::create([
            'nama' => 'Semester Uji',
            'tahun_akademik' => '2026/2027',
            'semester' => 'ganjil',
            'tanggal_mulai' => now()->subMonth()->toDateString(),
            'tanggal_akhir' => now()->addMonths(5)->toDateString(),
            'is_active' => true,
        ]);

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

    /** @param array<int, string> $nisList */
    private function createHalaqahDenganAnggota(User $musyrif, string $nama, array $nisList): Halaqah
    {
        $halaqah = Halaqah::create([
            'nama' => $nama,
            'musyrif_id' => $musyrif->id,
            'jenis' => 'tahfidz',
            'aktif' => true,
        ]);

        foreach ($nisList as $nis) {
            $santri = $this->createSantri($nis, null);
            HalaqahSantri::create([
                'halaqah_id' => $halaqah->id,
                'santri_id' => $santri->id,
                'joined_at' => now()->toDateString(),
            ]);
        }

        return $halaqah;
    }
}
