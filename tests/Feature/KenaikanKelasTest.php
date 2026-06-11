<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Akademik\Models\KeputusanKenaikan;
use Modules\Akademik\Models\Lembaga;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Services\KenaikanKelasService;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\PenempatanKelas;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-07 — Kenaikan Kelas & Sidang.
 *
 * (a) evaluasi mengikuti ambang Setting; 2 tenant ambang beda → rekomendasi
 *     beda (policy-first).
 * (b) santri pindah tengah tahun dievaluasi di kelas TERAKHIR (is_current).
 * (c) finalisasi 2× tidak menduplikasi penempatan (idempoten).
 * (d) override keputusan rapat tercatat (diputuskan_oleh + audit).
 * (e) mode mu'adalah (penempatan_mode=bebas) membuka semua kelas tujuan.
 */
class KenaikanKelasTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'settings', 'santri', 'kelas',
            'lembaga', 'periode_akademik', 'nilai', 'presensi_santri',
            'penempatan_kelas', 'kalender_akademik', 'jenis_pelanggaran',
            'pelanggaran_santri', 'keputusan_kenaikan', 'audit_log',
            'tahfidz_hafalan',
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

    private function svc(): KenaikanKelasService
    {
        return app(KenaikanKelasService::class);
    }

    /**
     * @return array{lembaga: Lembaga, k7: Kelas, k8: Kelas, periode: PeriodeAkademik}
     */
    private function seedDasar(string $suffix): array
    {
        $lembaga = Lembaga::create(['nama' => 'MTs '.$suffix, 'jenis' => 'formal']);

        $k7 = Kelas::create([
            'nama_kelas' => 'VII-'.$suffix, 'tingkat' => '7',
            'capacity' => 30, 'is_active' => true, 'lembaga_id' => $lembaga->id,
        ]);
        $k8 = Kelas::create([
            'nama_kelas' => 'VIII-'.$suffix, 'tingkat' => '8',
            'capacity' => 30, 'is_active' => true, 'lembaga_id' => $lembaga->id,
        ]);

        $periode = PeriodeAkademik::create([
            'nama' => 'Ganjil '.$suffix, 'tahun_akademik' => '2026/2027', 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01', 'tanggal_akhir' => '2026-12-31',
            'is_active' => true, 'lembaga_id' => $lembaga->id,
        ]);

        return compact('lembaga', 'k7', 'k8', 'periode');
    }

    private function santriDiKelas(string $nis, Kelas $kelas): Santri
    {
        $santri = Santri::create([
            'nis' => $nis, 'nama' => 'Santri '.$nis, 'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);

        PenempatanKelas::create([
            'santri_id' => $santri->id, 'kelas_id' => $kelas->id,
            'tahun_akademik' => '2026/2027', 'tanggal_penempatan' => '2026-07-01',
            'is_current' => true,
        ]);

        return $santri->fresh();
    }

    private function beriNilai(Santri $santri, Kelas $kelas, PeriodeAkademik $periode, float $nilaiAkhir): void
    {
        Nilai::create([
            'santri_id' => $santri->id,
            'pelajaran_id' => DB::connection('tenant')->table('pelajaran')->insertGetId([
                'tenant_id' => tenant()->getTenantKey(),
                'nama' => 'Mapel '.uniqid(), 'kode' => 'MP-'.uniqid(), 'sks' => 2,
                'created_at' => now(), 'updated_at' => now(),
            ]),
            'periode_akademik_id' => $periode->id,
            'kelas_id' => $kelas->id,
            'nilai_akhir' => $nilaiAkhir,
        ]);
    }

    // ---------------------------------------------------------------
    // (a) Policy-first: ambang Setting beda → rekomendasi beda
    // ---------------------------------------------------------------

    public function test_evaluasi_mengikuti_ambang_setting_dua_tenant_beda(): void
    {
        // Tenant A: default min_nilai 60 → nilai 70 cukup → naik.
        $this->bootTenant('kkna');
        $d = $this->seedDasar('A');
        $santri = $this->santriDiKelas('KA1', $d['k7']);
        $this->beriNilai($santri, $d['k7'], $d['periode'], 70);

        $hasil = $this->svc()->evaluasi($santri, $d['periode']);
        $this->assertSame('naik', $hasil['rekomendasi']);
        tenancy()->end();

        // Tenant B: ambang per-lembaga min_nilai 80 → nilai 70 kurang → tinggal.
        $this->bootTenant('kknb');
        $d = $this->seedDasar('B');
        Setting::create([
            'key' => KenaikanKelasService::SETTING_PREFIX.$d['lembaga']->id,
            'value' => ['min_nilai' => 80],
        ]);
        $santri = $this->santriDiKelas('KB1', $d['k7']);
        $this->beriNilai($santri, $d['k7'], $d['periode'], 70);

        $hasil = $this->svc()->evaluasi($santri, $d['periode']);
        $this->assertSame('tinggal', $hasil['rekomendasi']);
        $this->assertNotEmpty($hasil['alasan']);
        $this->assertStringContainsString('ambang', implode(' ', $hasil['alasan']));
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // (b) Pindah tengah tahun → dievaluasi di kelas TERAKHIR (is_current)
    // ---------------------------------------------------------------

    public function test_santri_pindah_tengah_tahun_dievaluasi_di_kelas_terakhir(): void
    {
        $this->bootTenant('kknp');
        $d = $this->seedDasar('P'); // k8 = tingkat tertinggi lembaga

        $santri = $this->santriDiKelas('KP1', $d['k7']);
        $this->beriNilai($santri, $d['k7'], $d['periode'], 85);

        // Pindah tengah tahun ke kelas 8 (observer WP-05 menutup yang lama).
        PenempatanKelas::create([
            'santri_id' => $santri->id, 'kelas_id' => $d['k8']->id,
            'tahun_akademik' => '2026/2027', 'tanggal_penempatan' => '2026-10-01',
            'alasan_mutasi' => 'Pindah tengah tahun', 'is_current' => true,
        ]);

        // Kelas terakhir = k8 = tingkat tertinggi → rekomendasi LULUS,
        // bukan 'naik' (yang akan terjadi bila dievaluasi di k7).
        $hasil = $this->svc()->evaluasi($santri->fresh(), $d['periode']);
        $this->assertSame('lulus', $hasil['rekomendasi']);
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // (c) Finalisasi 2× tidak menduplikasi penempatan (idempoten)
    // ---------------------------------------------------------------

    public function test_finalisasi_dua_kali_tidak_menduplikasi_penempatan(): void
    {
        $tenant = $this->bootTenant('kknf');
        $d = $this->seedDasar('F');
        $santri = $this->santriDiKelas('KF1', $d['k7']);
        $this->beriNilai($santri, $d['k7'], $d['periode'], 90);

        $user = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Mudir', 'email' => 'mudir@kknf.test',
            'password' => 'password123',
        ]);

        $hasil = $this->svc()->evaluasi($santri, $d['periode']);
        $this->svc()->simpanDraft([
            'santri_id' => $santri->id,
            'periode_akademik_id' => $d['periode']->id,
            'kelas_asal_id' => $d['k7']->id,
            'kelas_tujuan_id' => $d['k8']->id,
            'rekomendasi' => $hasil['rekomendasi'],
            'keputusan' => 'naik',
            'alasan' => $hasil['alasan'],
        ], $user);

        $jumlah1 = $this->svc()->finalisasi($d['periode'], $d['k7'], $user);
        $this->assertSame(1, $jumlah1);

        // Finalisasi KEDUA: tidak ada draft tersisa → 0, tanpa duplikasi.
        $jumlah2 = $this->svc()->finalisasi($d['periode'], $d['k7'], $user);
        $this->assertSame(0, $jumlah2);

        $this->assertSame(1, PenempatanKelas::query()
            ->where('santri_id', $santri->id)->where('kelas_id', $d['k8']->id)->count());

        // Observer WP-05: penempatan lama tertutup + kelas_id tersinkron.
        $this->assertSame(1, PenempatanKelas::query()
            ->where('santri_id', $santri->id)->where('is_current', true)->count());
        $this->assertSame($d['k8']->id, $santri->fresh()->kelas_id);

        $keputusan = KeputusanKenaikan::query()->where('santri_id', $santri->id)->firstOrFail();
        $this->assertNotNull($keputusan->finalized_at);
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // (d) Override keputusan tercatat: diputuskan_oleh + audit
    // ---------------------------------------------------------------

    public function test_override_keputusan_tercatat_dengan_pemutus_dan_audit(): void
    {
        $tenant = $this->bootTenant('kkno');
        $d = $this->seedDasar('O');
        $santri = $this->santriDiKelas('KO1', $d['k7']);
        $this->beriNilai($santri, $d['k7'], $d['periode'], 40); // rekomendasi mesin: tinggal

        $user = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Mudir O', 'email' => 'mudir@kkno.test',
            'password' => 'password123',
        ]);

        $hasil = $this->svc()->evaluasi($santri, $d['periode']);
        $this->assertSame('tinggal', $hasil['rekomendasi']);

        // Rapat MENG-OVERRIDE: tetap naikkan dengan catatan pembinaan.
        $keputusan = $this->svc()->simpanDraft([
            'santri_id' => $santri->id,
            'periode_akademik_id' => $d['periode']->id,
            'kelas_asal_id' => $d['k7']->id,
            'kelas_tujuan_id' => $d['k8']->id,
            'rekomendasi' => $hasil['rekomendasi'],
            'keputusan' => 'naik',
            'alasan' => $hasil['alasan'],
            'catatan' => 'Naik bersyarat — wajib bimbingan semester depan.',
        ], $user);

        $this->assertSame('tinggal', $keputusan->rekomendasi);
        $this->assertSame('naik', $keputusan->keputusan);
        $this->assertSame($user->id, $keputusan->diputuskan_oleh);
        $this->assertSame('Naik bersyarat — wajib bimbingan semester depan.', $keputusan->catatan);

        // Jejak audit (trait Auditable) tercatat utk keputusan_kenaikan.
        $this->assertTrue(
            DB::connection('tenant')->table('audit_log')
                ->where('auditable_type', KeputusanKenaikan::class)
                ->where('auditable_id', $keputusan->id)
                ->exists(),
        );
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // (e) Mode mu'adalah: penempatan_mode=bebas membuka semua kelas tujuan
    // ---------------------------------------------------------------

    public function test_mode_bebas_membuka_semua_kelas_tujuan(): void
    {
        $this->bootTenant('kknm');
        $d = $this->seedDasar('M');

        // Default berjenjang: dari tingkat 7 hanya tingkat 8.
        $opsi = $this->svc()->kelasTujuanOptions($d['k7']);
        $this->assertSame([$d['k8']->id], $opsi->pluck('id')->all());

        // Mode bebas: semua kelas aktif lembaga (termasuk turun/tetap).
        Setting::create([
            'key' => KenaikanKelasService::SETTING_MODE_PENEMPATAN,
            'value' => 'bebas',
        ]);
        $opsi = $this->svc()->kelasTujuanOptions($d['k7']);
        $this->assertEqualsCanonicalizing([$d['k7']->id, $d['k8']->id], $opsi->pluck('id')->all());
        tenancy()->end();
    }
}
