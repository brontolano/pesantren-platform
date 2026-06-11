<?php

namespace Tests\Feature;

use App\Models\AccessWali;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Akademik\Models\Lembaga;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\RaporDiniyah;
use Modules\Akademik\Models\RubrikKriteria;
use Modules\Akademik\Models\RubrikSkor;
use Modules\Akademik\Services\RaporDiniyahService;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Models\HafalanTahfidz;
use Modules\Tahfidz\Models\PenilaianTahfidz;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-10 — Rapor Diniyah: snapshot benar + idempoten, blok dimatikan via
 * Setting tak tercetak, santri tanpa tahfidz → blok kosong ramah,
 * otorisasi wali (403 anak orang lain), 2 tenant blok beda → cetak beda.
 */
class RaporDiniyahTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions',
            'santri', 'kelas', 'lembaga', 'periode_akademik', 'pelajaran', 'nilai',
            'rubrik_kriteria', 'rubrik_skor', 'rapor_diniyah',
            'tahfidz_hafalan', 'tahfidz_penilaian', 'capaian_kitab',
            'kegiatan', 'presensi_kegiatan', 'pelanggaran_santri', 'access_wali', 'settings',
        ]);

        $this->truncatePpdbTables([
            'rapor_diniyah', 'rubrik_skor', 'rubrik_kriteria',
            'tahfidz_penilaian', 'tahfidz_hafalan',
            'presensi_kegiatan', 'kegiatan',
            'nilai', 'pelajaran', 'periode_akademik',
            'access_wali', 'santri', 'kelas', 'lembaga', 'settings',
            'model_has_roles', 'model_has_permissions', 'role_has_permissions',
            'roles', 'permissions', 'users', 'domains', 'tenants',
        ]);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    /**
     * @return array{tenant: Tenant, periode: PeriodeAkademik, kelas: Kelas, santri: Santri}
     */
    private function buatFixtureDiniyah(string $prefix): array
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);

        $lembaga = Lembaga::create([
            'tenant_id' => $tenant->id,
            'nama' => 'Madrasah Diniyah',
            'jenis' => 'diniyah',
            'aktif' => true,
        ]);

        $kelas = Kelas::create([
            'tenant_id' => $tenant->id,
            'nama_kelas' => 'Ula A',
            'tingkat' => '1',
            'capacity' => 30,
            'is_active' => true,
            'lembaga_id' => $lembaga->id,
        ]);

        $periode = PeriodeAkademik::create([
            'tenant_id' => $tenant->id,
            'nama' => 'Semester Ganjil',
            'tahun_akademik' => '2026/2027',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_akhir' => '2026-06-30',
            'is_active' => true,
        ]);

        $santri = Santri::create([
            'tenant_id' => $tenant->id,
            'nis' => strtoupper($prefix).'-001',
            'nama' => 'Santri Diniyah',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'kelas_id' => $kelas->id,
        ]);

        return compact('tenant', 'periode', 'kelas', 'santri');
    }

    private function isiDataBlok(array $fx): void
    {
        $pelajaran = Pelajaran::create([
            'tenant_id' => $fx['tenant']->id,
            'nama' => 'Nahwu',
            'kode' => 'NHW',
            'sks' => 2,
        ]);

        Nilai::create([
            'tenant_id' => $fx['tenant']->id,
            'santri_id' => $fx['santri']->id,
            'pelajaran_id' => $pelajaran->id,
            'periode_akademik_id' => $fx['periode']->id,
            'kelas_id' => $fx['kelas']->id,
            'nilai_harian' => 80,
            'nilai_uts' => 80,
            'nilai_uas' => 80,
        ]);

        $k1 = RubrikKriteria::create([
            'tenant_id' => $fx['tenant']->id,
            'nama' => 'Sholat Berjamaah',
            'bobot' => 2,
            'is_active' => true,
        ]);
        $k2 = RubrikKriteria::create([
            'tenant_id' => $fx['tenant']->id,
            'nama' => 'Adab kepada Guru',
            'bobot' => 1,
            'is_active' => true,
        ]);

        RubrikSkor::create([
            'tenant_id' => $fx['tenant']->id,
            'kriteria_id' => $k1->id,
            'santri_id' => $fx['santri']->id,
            'periode_akademik_id' => $fx['periode']->id,
            'skor' => 90,
        ]);
        RubrikSkor::create([
            'tenant_id' => $fx['tenant']->id,
            'kriteria_id' => $k2->id,
            'santri_id' => $fx['santri']->id,
            'periode_akademik_id' => $fx['periode']->id,
            'skor' => 60,
        ]);

        $hafalan = HafalanTahfidz::create([
            'tenant_id' => $fx['tenant']->id,
            'santri_id' => $fx['santri']->id,
            'surah' => 'An-Naba',
            'ayat_awal' => 1,
            'ayat_akhir' => 40,
            'tanggal_selesai' => '2026-03-10',
            'status' => 'selesai',
        ]);

        PenilaianTahfidz::create([
            'tenant_id' => $fx['tenant']->id,
            'santri_id' => $fx['santri']->id,
            'hafalan_id' => $hafalan->id,
            'jenis' => 'setoran',
            'nilai' => 85,
            'tanggal' => '2026-03-10',
        ]);
    }

    public function test_generate_snapshot_benar_dan_idempoten(): void
    {
        $fx = $this->buatFixtureDiniyah('rd-gen');
        $this->isiDataBlok($fx);

        $service = app(RaporDiniyahService::class);
        $service->generate($fx['santri'], $fx['periode']);
        $rapor = $service->generate($fx['santri'], $fx['periode']); // 2× → tetap 1 baris

        $this->assertSame(1, RaporDiniyah::query()->count());

        $blok = $rapor->fresh()->blok;

        // nilai_imtihan: rumus NilaiService default 30/30/40 atas 80/80/80 = 80.
        $this->assertCount(1, $blok['nilai_imtihan']['daftar']);
        $this->assertSame('Nahwu', $blok['nilai_imtihan']['daftar'][0]['pelajaran']);
        $this->assertEqualsWithDelta(80.0, $blok['nilai_imtihan']['rata_rata'], 0.01);

        // ubudiyah_akhlak: (90×2 + 60×1) / 3 = 80.
        $this->assertEqualsWithDelta(80.0, $blok['ubudiyah_akhlak']['agregat'], 0.01);
        $this->assertCount(2, $blok['ubudiyah_akhlak']['daftar']);

        // tahfidz: 1 setoran selesai dalam periode, nilai rata 85.
        $this->assertSame(1, $blok['tahfidz']['jumlah_setoran']);
        $this->assertSame('An-Naba', $blok['tahfidz']['terakhir']['surah']);
        $this->assertEqualsWithDelta(85.0, $blok['tahfidz']['nilai_rata'], 0.01);

        // disiplin & kehadiran: tanpa data → angka nol ramah, bukan error.
        $this->assertSame(0, $blok['disiplin']['poin_bersih']);
        $this->assertSame(0, $blok['kehadiran_kegiatan']['hadir']);
        $this->assertSame('presensi_kegiatan', $blok['kehadiran_kegiatan']['sumber']);

        // Idempoten DAN ter-update: ubah skor, generate ulang → snapshot baru.
        RubrikSkor::query()->where('santri_id', $fx['santri']->id)->update(['skor' => 100]);
        $rapor = $service->generate($fx['santri'], $fx['periode']);

        $this->assertSame(1, RaporDiniyah::query()->count());
        $this->assertEqualsWithDelta(100.0, $rapor->fresh()->blok['ubudiyah_akhlak']['agregat'], 0.01);
    }

    public function test_santri_tanpa_data_tahfidz_blok_kosong_bukan_error(): void
    {
        $fx = $this->buatFixtureDiniyah('rd-kosong');

        $rapor = app(RaporDiniyahService::class)->generate($fx['santri'], $fx['periode']);
        $blok = $rapor->fresh()->blok;

        $this->assertSame(0, $blok['tahfidz']['jumlah_setoran']);
        $this->assertNull($blok['tahfidz']['terakhir']);
        $this->assertNull($blok['tahfidz']['nilai_rata']);
        $this->assertSame([], $blok['nilai_imtihan']['daftar']);
        $this->assertSame([], $blok['capaian_kitab']['daftar']);
    }

    public function test_blok_dimatikan_via_setting_tidak_tercetak(): void
    {
        $fx = $this->buatFixtureDiniyah('rd-blok');
        $this->isiDataBlok($fx);

        $rapor = app(RaporDiniyahService::class)->generate($fx['santri'], $fx['periode']);

        // Default: semua blok tampil.
        $this->assertSame(array_keys(RaporDiniyah::BLOK), RaporDiniyah::blokAktif());

        Setting::create([
            'tenant_id' => $fx['tenant']->id,
            'key' => RaporDiniyah::SETTING_BLOK,
            'value' => ['nilai_imtihan', 'disiplin'],
        ]);

        $html = view('exports.rapor-diniyah-print', [
            'tenantName' => $fx['tenant']->name,
            'rapor' => $rapor->load(['santri.kelas', 'periodeAkademik']),
            'periode' => $rapor->periodeAkademik,
            'blokAktif' => RaporDiniyah::blokAktif(),
            'printedAt' => now(),
        ])->render();

        $this->assertStringContainsString('Nilai Imtihan', $html);
        $this->assertStringContainsString('Disiplin', $html);
        $this->assertStringNotContainsString('Ubudiyah', $html);
        $this->assertStringNotContainsString('Capaian Kitab', $html);
        $this->assertStringNotContainsString('Kehadiran Kegiatan', $html);
        $this->assertStringNotContainsString('Jumlah Setoran', $html); // blok tahfidz
    }

    public function test_dua_tenant_blok_beda_cetak_beda(): void
    {
        // Tenant A: default (semua blok) → Tahfidz tercetak.
        $fxA = $this->buatFixtureDiniyah('rd-ta');
        $raporA = app(RaporDiniyahService::class)->generate($fxA['santri'], $fxA['periode']);
        $htmlA = view('exports.rapor-diniyah-print', [
            'tenantName' => $fxA['tenant']->name,
            'rapor' => $raporA->load(['santri.kelas', 'periodeAkademik']),
            'periode' => $raporA->periodeAkademik,
            'blokAktif' => RaporDiniyah::blokAktif(),
            'printedAt' => now(),
        ])->render();
        tenancy()->end();

        // Tenant B: kebijakan hanya nilai_imtihan → Tahfidz TIDAK tercetak.
        $fxB = $this->buatFixtureDiniyah('rd-tb');
        Setting::create([
            'tenant_id' => $fxB['tenant']->id,
            'key' => RaporDiniyah::SETTING_BLOK,
            'value' => ['nilai_imtihan'],
        ]);
        $raporB = app(RaporDiniyahService::class)->generate($fxB['santri'], $fxB['periode']);
        $htmlB = view('exports.rapor-diniyah-print', [
            'tenantName' => $fxB['tenant']->name,
            'rapor' => $raporB->load(['santri.kelas', 'periodeAkademik']),
            'periode' => $raporB->periodeAkademik,
            'blokAktif' => RaporDiniyah::blokAktif(),
            'printedAt' => now(),
        ])->render();

        $this->assertStringContainsString('Tahfidz', $htmlA);
        $this->assertStringNotContainsString('Tahfidz', $htmlB);
        $this->assertStringContainsString('Nilai Imtihan', $htmlB);
    }

    public function test_wali_hanya_bisa_unduh_rapor_diniyah_anaknya_sendiri(): void
    {
        $fx = $this->buatFixtureDiniyah('rd-wali');
        $tenant = $fx['tenant'];
        $host = $tenant->domains->first()->domain;

        $wali = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Wali Sendiri',
            'email' => 'wali-rd@test.local',
            'password' => 'password',
        ]);
        $wali->assignRole('wali');

        AccessWali::create([
            'tenant_id' => $tenant->id,
            'wali_id' => $wali->id,
            'santri_id' => $fx['santri']->id,
            'relationship_type' => 'ayah',
            'is_primary' => true,
        ]);

        $anakLain = Santri::create([
            'tenant_id' => $tenant->id,
            'nis' => 'RD-LAIN',
            'nama' => 'Anak Orang Lain',
            'tanggal_lahir' => '2012-02-01',
            'jenis_kelamin' => 'P',
            'status' => 'aktif',
            'kelas_id' => $fx['kelas']->id,
        ]);

        $service = app(RaporDiniyahService::class);
        $raporSendiri = $service->generate($fx['santri'], $fx['periode']);
        $raporLain = $service->generate($anakLain, $fx['periode']);
        tenancy()->end();

        // Anak sendiri → 200 (halaman cetak).
        $this->actingAs($wali)
            ->get("http://{$host}/wali/santri/{$fx['santri']->id}/rapor-diniyah/{$raporSendiri->id}/download", [
                'HTTP_HOST' => $host,
                'SERVER_NAME' => $host,
            ])
            ->assertOk()
            ->assertSee('Rapor Diniyah');

        // Rapor anak orang lain → 403.
        $this->actingAs($wali)
            ->get("http://{$host}/wali/santri/{$fx['santri']->id}/rapor-diniyah/{$raporLain->id}/download", [
                'HTTP_HOST' => $host,
                'SERVER_NAME' => $host,
            ])
            ->assertForbidden();
    }
}
