<?php

namespace Tests\Feature;

use Modules\Akademik\Filament\Resources\JadwalPelajaran\JadwalPelajaranResource;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Kurikulum;
use Modules\Akademik\Models\KurikulumMapel;
use Modules\Akademik\Models\Lembaga;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Services\KurikulumService;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-06 — Kurikulum per Tingkat.
 *
 * (a) Tenant TANPA kurikulum → perilaku lama utuh (jadwal & nilai jalan,
 *     helper warning false).
 * (b) Dua lembaga dengan kurikulum berbeda tidak saling bocor.
 * (c) Buka Periode idempoten (2x panggil = jumlah baris nilai sama).
 * (d) Mapel non-kurikulum di jadwal → warning true tapi TETAP tersimpan.
 *
 * Pola baku: createRuntimeTenant (DB fisik per tenant, migrasi otomatis).
 */
class KurikulumTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'kelas',
            'lembaga', 'periode_akademik', 'pelajaran', 'jadwal_pelajaran',
            'nilai', 'kitab', 'kurikulum', 'kurikulum_mapel',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function bootTenant(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        return $tenant;
    }

    /** @return array{lembaga:Lembaga, kelas:Kelas, santri:Santri, pelajaran:Pelajaran, periode:PeriodeAkademik} */
    private function seedAkademikDasar(string $suffix, string $tingkat = '7'): array
    {
        $lembaga = Lembaga::create(['nama' => 'MTs '.$suffix, 'jenis' => 'formal']);

        $kelas = Kelas::create([
            'nama_kelas' => 'VII-'.$suffix, 'tingkat' => $tingkat,
            'capacity' => 30, 'is_active' => true, 'lembaga_id' => $lembaga->id,
        ]);

        $santri = Santri::create([
            'nis' => 'KR'.$suffix, 'nama' => 'Santri '.$suffix,
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L',
            'status' => 'aktif', 'kelas_id' => $kelas->id,
        ]);

        $pelajaran = Pelajaran::create(['nama' => 'Fiqih '.$suffix, 'kode' => 'FQH-'.$suffix, 'sks' => 2]);

        $periode = PeriodeAkademik::create([
            'nama' => 'Ganjil '.$suffix, 'tahun_akademik' => '2026/2027', 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01', 'tanggal_akhir' => '2026-12-31',
            'is_active' => true, 'lembaga_id' => $lembaga->id,
        ]);

        return compact('lembaga', 'kelas', 'santri', 'pelajaran', 'periode');
    }

    // ---------------------------------------------------------------
    // (a) Tenant tanpa kurikulum → perilaku lama utuh
    // ---------------------------------------------------------------

    public function test_tenant_tanpa_kurikulum_jadwal_dan_nilai_tetap_jalan(): void
    {
        $this->bootTenant('krnk');
        $d = $this->seedAkademikDasar('A');

        // Helper warning: TIDAK pernah true tanpa kurikulum.
        $svc = app(KurikulumService::class);
        $this->assertFalse($svc->isMapelDiLuarKurikulum($d['kelas']->id, $d['pelajaran']->id));

        // Jadwal tetap tersimpan.
        $jadwal = JadwalPelajaran::create([
            'kelas_id' => $d['kelas']->id, 'pelajaran_id' => $d['pelajaran']->id,
            'periode_akademik_id' => $d['periode']->id,
            'hari' => 'senin', 'jam_mulai' => '07:00', 'jam_selesai' => '08:30',
        ]);
        $this->assertNotNull($jadwal->id);

        // Nilai tetap bisa diinput manual seperti biasa.
        $nilai = Nilai::create([
            'santri_id' => $d['santri']->id, 'pelajaran_id' => $d['pelajaran']->id,
            'periode_akademik_id' => $d['periode']->id, 'kelas_id' => $d['kelas']->id,
            'nilai_harian' => 80,
        ]);
        $this->assertNotNull($nilai->id);
    }

    // ---------------------------------------------------------------
    // (b) Dua lembaga, kurikulum tidak saling bocor
    // ---------------------------------------------------------------

    public function test_kurikulum_dua_lembaga_tidak_saling_bocor(): void
    {
        $this->bootTenant('krdl');
        $formal = $this->seedAkademikDasar('F', '7');
        $diniyah = $this->seedAkademikDasar('D', 'ula 1');

        $kurFormal = Kurikulum::create(['nama' => 'Kurikulum Formal', 'lembaga_id' => $formal['lembaga']->id, 'aktif' => true]);
        KurikulumMapel::create([
            'kurikulum_id' => $kurFormal->id, 'pelajaran_id' => $formal['pelajaran']->id,
            'tingkat' => '7', 'jam_per_minggu' => 2, 'is_wajib' => true,
        ]);

        $kurDiniyah = Kurikulum::create(['nama' => 'Kurikulum Diniyah', 'lembaga_id' => $diniyah['lembaga']->id, 'aktif' => true]);
        KurikulumMapel::create([
            'kurikulum_id' => $kurDiniyah->id, 'pelajaran_id' => $diniyah['pelajaran']->id,
            'tingkat' => 'ula 1', 'jam_per_minggu' => 4, 'is_wajib' => true,
        ]);

        $svc = app(KurikulumService::class);

        // Mapel formal sah di kelas formal, dianggap "di luar" di kelas diniyah... tapi
        // hanya bila tingkat kelas diniyah diatur kurikulum diniyah (ya: 'ula 1').
        $this->assertFalse($svc->isMapelDiLuarKurikulum($formal['kelas']->id, $formal['pelajaran']->id));
        $this->assertTrue($svc->isMapelDiLuarKurikulum($diniyah['kelas']->id, $formal['pelajaran']->id));
        $this->assertFalse($svc->isMapelDiLuarKurikulum($diniyah['kelas']->id, $diniyah['pelajaran']->id));

        // Buka periode formal: hanya santri lembaga formal yang dapat baris nilai.
        $hasil = $svc->bukaPeriode($kurFormal);
        $this->assertSame(1, $hasil['dibuat']);
        $this->assertSame(1, Nilai::query()->count());
        $this->assertSame($formal['santri']->id, Nilai::query()->first()->santri_id);
        $this->assertSame($formal['periode']->id, Nilai::query()->first()->periode_akademik_id);

        // Buka periode diniyah: baris nilai milik santri diniyah, periode diniyah.
        $hasil2 = $svc->bukaPeriode($kurDiniyah);
        $this->assertSame(1, $hasil2['dibuat']);
        $this->assertSame(2, Nilai::query()->count());
        $this->assertSame(1, Nilai::query()->where('santri_id', $diniyah['santri']->id)
            ->where('periode_akademik_id', $diniyah['periode']->id)->count());
    }

    public function test_unique_kurikulum_pelajaran_tingkat_ditolak_dobel(): void
    {
        $this->bootTenant('krUq');
        $d = $this->seedAkademikDasar('U');

        $kur = Kurikulum::create(['nama' => 'K', 'lembaga_id' => $d['lembaga']->id]);
        KurikulumMapel::create(['kurikulum_id' => $kur->id, 'pelajaran_id' => $d['pelajaran']->id, 'tingkat' => '7']);

        $this->expectException(QueryException::class);
        KurikulumMapel::create(['kurikulum_id' => $kur->id, 'pelajaran_id' => $d['pelajaran']->id, 'tingkat' => '7']);
    }

    // ---------------------------------------------------------------
    // (c) Buka Periode idempoten
    // ---------------------------------------------------------------

    public function test_buka_periode_idempoten_dua_kali_klik_jumlah_sama(): void
    {
        $this->bootTenant('krid');
        $d = $this->seedAkademikDasar('I');

        // Mapel kedua: PILIHAN — tidak boleh ikut digenerate.
        $pilihan = Pelajaran::create(['nama' => 'Kaligrafi', 'kode' => 'KLG-I', 'sks' => 1]);

        $kur = Kurikulum::create(['nama' => 'Kurikulum I', 'lembaga_id' => $d['lembaga']->id, 'aktif' => true]);
        KurikulumMapel::create(['kurikulum_id' => $kur->id, 'pelajaran_id' => $d['pelajaran']->id, 'tingkat' => '7', 'is_wajib' => true]);
        KurikulumMapel::create(['kurikulum_id' => $kur->id, 'pelajaran_id' => $pilihan->id, 'tingkat' => '7', 'is_wajib' => false]);

        $svc = app(KurikulumService::class);

        $hasil1 = $svc->bukaPeriode($kur);
        $this->assertSame(1, $hasil1['dibuat'], 'Hanya mapel WAJIB yang digenerate');
        $this->assertSame(1, Nilai::query()->count());

        // Isi nilai di baris yang sudah ada — klik kedua TIDAK boleh menimpa.
        Nilai::query()->first()->update(['nilai_harian' => 90]);

        $hasil2 = $svc->bukaPeriode($kur);
        $this->assertSame(0, $hasil2['dibuat'], 'Klik kedua tidak membuat baris baru');
        $this->assertSame(1, $hasil2['dilewati']);
        $this->assertSame(1, Nilai::query()->count(), 'Jumlah baris nilai tetap sama');
        $this->assertEquals(90.0, Nilai::query()->first()->nilai_harian, 'Nilai yang sudah diisi tidak tertimpa');
    }

    public function test_buka_periode_tanpa_periode_aktif_melempar_error_jelas(): void
    {
        $this->bootTenant('krna');
        $lembaga = Lembaga::create(['nama' => 'MTs NA', 'jenis' => 'formal']);
        $kur = Kurikulum::create(['nama' => 'K-NA', 'lembaga_id' => $lembaga->id]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('periode akademik aktif');

        app(KurikulumService::class)->bukaPeriode($kur);
    }

    // ---------------------------------------------------------------
    // (d) Mapel non-kurikulum di jadwal → warning true, tetap tersimpan
    // ---------------------------------------------------------------

    public function test_mapel_non_kurikulum_warning_true_tapi_jadwal_tetap_tersimpan(): void
    {
        $this->bootTenant('krwj');
        $d = $this->seedAkademikDasar('W');

        $kur = Kurikulum::create(['nama' => 'Kurikulum W', 'lembaga_id' => $d['lembaga']->id, 'aktif' => true]);
        KurikulumMapel::create(['kurikulum_id' => $kur->id, 'pelajaran_id' => $d['pelajaran']->id, 'tingkat' => '7', 'is_wajib' => true]);

        $liar = Pelajaran::create(['nama' => 'Mapel Liar', 'kode' => 'LIAR-W', 'sks' => 1]);

        // Warning flag true (helper resource + service).
        $svc = app(KurikulumService::class);
        $this->assertTrue($svc->isMapelDiLuarKurikulum($d['kelas']->id, $liar->id));
        $this->assertNotNull(
            JadwalPelajaranResource::kurikulumWarning($d['kelas']->id, $liar->id)
        );

        // TIDAK memblokir: jadwal tetap tersimpan.
        $jadwal = JadwalPelajaran::create([
            'kelas_id' => $d['kelas']->id, 'pelajaran_id' => $liar->id,
            'periode_akademik_id' => $d['periode']->id,
            'hari' => 'selasa', 'jam_mulai' => '09:00', 'jam_selesai' => '10:30',
        ]);
        $this->assertNotNull($jadwal->id);
        $this->assertSame(1, JadwalPelajaran::query()->where('pelajaran_id', $liar->id)->count());

        // Kurikulum nonaktif → warning padam (validasi hanya pakai yang aktif).
        $kur->update(['aktif' => false]);
        $this->assertFalse($svc->isMapelDiLuarKurikulum($d['kelas']->id, $liar->id));
    }
}
