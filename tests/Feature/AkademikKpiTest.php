<?php

namespace Tests\Feature;

use App\Models\Setting;
use InvalidArgumentException;
use Modules\Akademik\Models\RubrikKriteria;
use Modules\Akademik\Services\PresensiSantriService;
use Modules\Akademik\Services\RubrikService;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Models\HafalanTahfidz;
use Modules\Tahfidz\Services\TahfidzKpiService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G3-18 — Presensi harian + threshold, rubrik berbobot, dan KPI Tahfidz.
 * Fokus pada KEBENARAN AGREGASI (pembobotan, div-by-zero, threshold).
 */
class AkademikKpiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'kelas', 'pelajaran',
            'presensi_santri', 'rubrik_kriteria', 'rubrik_skor', 'tahfidz_target',
            'tahfidz_hafalan', 'settings', 'audit_log',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    private function santri(string $nis = 'S1'): Santri
    {
        return Santri::create([
            'nis' => $nis, 'nama' => 'Santri '.$nis, 'tanggal_lahir' => '2011-01-01',
            'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);
    }

    public function test_presensi_rekap_dan_threshold(): void
    {
        $this->boot('akp1');
        $svc = app(PresensiSantriService::class);
        $s = $this->santri();
        // 4 hadir + 1 alpa = 80%.
        foreach (['2026-06-01', '2026-06-02', '2026-06-03', '2026-06-04'] as $d) {
            $svc->catat($s, $d, 'hadir');
        }
        $svc->catat($s, '2026-06-05', 'alpa');

        $rekap = $svc->rekap($s, '2026-06-01', '2026-06-30');
        $this->assertSame(5, $rekap['total']);
        $this->assertSame(4, $rekap['hadir']);
        $this->assertEqualsWithDelta(80, $rekap['persen'], 0.01);
        $this->assertTrue($svc->memenuhiThreshold($s, '2026-06-01', '2026-06-30')); // 80 >= 80
        tenancy()->end();
    }

    public function test_presensi_idempoten_dan_threshold_gagal(): void
    {
        $this->boot('akp2');
        $svc = app(PresensiSantriService::class);
        $s = $this->santri();
        $svc->catat($s, '2026-06-01', 'hadir');
        $svc->catat($s, '2026-06-01', 'alpa'); // update hari yang sama (idempoten per tanggal)
        $svc->catat($s, '2026-06-02', 'alpa');

        $rekap = $svc->rekap($s, '2026-06-01', '2026-06-30');
        $this->assertSame(2, $rekap['total']); // bukan 3
        $this->assertSame(0, $rekap['hadir']);
        $this->assertFalse($svc->memenuhiThreshold($s, '2026-06-01', '2026-06-30'));
        // Tanpa data → konservatif false.
        $this->assertFalse($svc->memenuhiThreshold($this->santri('S2'), '2026-06-01', '2026-06-30'));
        tenancy()->end();
    }

    public function test_rubrik_agregat_berbobot(): void
    {
        $this->boot('akp3');
        $svc = app(RubrikService::class);
        $s = $this->santri();
        $k1 = RubrikKriteria::create(['nama' => 'Kefasihan', 'bobot' => 2, 'is_active' => true]);
        $k2 = RubrikKriteria::create(['nama' => 'Tajwid', 'bobot' => 3, 'is_active' => true]);
        $svc->catatSkor($k1, $s, 80);
        $svc->catatSkor($k2, $s, 90);

        // (80×2 + 90×3) / (2+3) = 430/5 = 86.
        $this->assertEqualsWithDelta(86, $svc->agregat($s), 0.01);
        tenancy()->end();
    }

    public function test_rubrik_bobot_nol_dan_nonaktif_diabaikan(): void
    {
        $this->boot('akp4');
        $svc = app(RubrikService::class);
        $s = $this->santri();
        $k1 = RubrikKriteria::create(['nama' => 'A', 'bobot' => 2, 'is_active' => true]);
        $kNol = RubrikKriteria::create(['nama' => 'Nol', 'bobot' => 0, 'is_active' => true]);
        $kOff = RubrikKriteria::create(['nama' => 'Off', 'bobot' => 5, 'is_active' => false]);
        $svc->catatSkor($k1, $s, 70);
        $svc->catatSkor($kNol, $s, 100); // bobot 0 → diabaikan
        $svc->catatSkor($kOff, $s, 100); // nonaktif → diabaikan

        // Hanya k1 → agregat 70 (bukan terkontaminasi 100).
        $this->assertEqualsWithDelta(70, $svc->agregat($s), 0.01);
        tenancy()->end();
    }

    public function test_rubrik_semua_bobot_nol_tidak_div_by_zero(): void
    {
        $this->boot('akp5');
        $svc = app(RubrikService::class);
        $s = $this->santri();
        $k = RubrikKriteria::create(['nama' => 'X', 'bobot' => 0, 'is_active' => true]);
        $svc->catatSkor($k, $s, 95);

        $this->assertEqualsWithDelta(0, $svc->agregat($s), 0.01); // aman, bukan NaN/error
        tenancy()->end();
    }

    public function test_rubrik_skor_invalid_ditolak(): void
    {
        $this->boot('akp6');
        $svc = app(RubrikService::class);
        $k = RubrikKriteria::create(['nama' => 'X', 'bobot' => 1, 'is_active' => true]);
        $this->expectException(InvalidArgumentException::class);
        $svc->catatSkor($k, $this->santri(), 150); // > 100
        tenancy()->end();
    }

    public function test_tahfidz_kpi_capaian(): void
    {
        $this->boot('akp7');
        $svc = app(TahfidzKpiService::class);
        $s = $this->santri();
        $svc->setTarget($s, '2026', 5);
        foreach (['Al-Fatihah', 'An-Naba', 'An-Naziat'] as $surah) {
            HafalanTahfidz::create(['santri_id' => $s->id, 'surah' => $surah, 'status' => 'selesai', 'tanggal_selesai' => '2026-03-01']);
        }
        HafalanTahfidz::create(['santri_id' => $s->id, 'surah' => 'Abasa', 'status' => 'berjalan']);

        $hasil = $svc->capaian($s, '2026');
        $this->assertSame(5, $hasil['target']);
        $this->assertSame(3, $hasil['realisasi']); // hanya 'selesai' di 2026
        $this->assertEqualsWithDelta(60, $hasil['capaian'], 0.01); // 3/5
        tenancy()->end();
    }

    public function test_tahfidz_capaian_per_periode_tidak_lintas_tahun(): void
    {
        $this->boot('akp7b');
        $svc = app(TahfidzKpiService::class);
        $s = $this->santri();
        $svc->setTarget($s, '2026', 5);
        // 2 selesai tahun lalu (2025) + 4 selesai tahun berjalan (2026).
        foreach (['A', 'B'] as $x) {
            HafalanTahfidz::create(['santri_id' => $s->id, 'surah' => $x, 'status' => 'selesai', 'tanggal_selesai' => '2025-05-01']);
        }
        foreach (['C', 'D', 'E', 'F'] as $x) {
            HafalanTahfidz::create(['santri_id' => $s->id, 'surah' => $x, 'status' => 'selesai', 'tanggal_selesai' => '2026-04-01']);
        }
        // Capaian 2026 HANYA hitung yang selesai 2026 (4), bukan 6 → 80%, bukan 120%.
        $this->assertSame(4, $svc->capaian($s, '2026')['realisasi']);
        $this->assertEqualsWithDelta(80, $svc->capaian($s, '2026')['capaian'], 0.01);
        // Kumulatif (tanpa periode) tetap 6.
        $this->assertSame(6, $svc->realisasi($s));
        tenancy()->end();
    }

    public function test_ambang_kehadiran_menghormati_setting_bentuk_array(): void
    {
        $this->boot('akp7c');
        $svc = app(PresensiSantriService::class);
        // Setting disimpan sebagai array (pola umum) → harus dihormati, bukan default 80.
        Setting::create(['key' => 'akademik_kehadiran_minimal', 'value' => ['value' => 90]]);
        $this->assertSame(90, $svc->ambang());

        $s = $this->santri();
        // 4 hadir / 5 = 80% < ambang 90 → TIDAK memenuhi.
        foreach (['2026-06-01', '2026-06-02', '2026-06-03', '2026-06-04'] as $d) {
            $svc->catat($s, $d, 'hadir');
        }
        $svc->catat($s, '2026-06-05', 'alpa');
        $this->assertFalse($svc->memenuhiThreshold($s, '2026-06-01', '2026-06-30'));
        tenancy()->end();
    }

    public function test_tahfidz_target_nol_capaian_nol(): void
    {
        $this->boot('akp8');
        $svc = app(TahfidzKpiService::class);
        $s = $this->santri();
        HafalanTahfidz::create(['santri_id' => $s->id, 'surah' => 'X', 'status' => 'selesai']);

        $hasil = $svc->capaian($s, '2099'); // tak ada target
        $this->assertSame(0, $hasil['target']);
        $this->assertEqualsWithDelta(0, $hasil['capaian'], 0.01); // tak div-by-zero
        tenancy()->end();
    }

    public function test_isolasi_tenant_akademik_kpi(): void
    {
        $this->boot('akpA');
        RubrikKriteria::create(['nama' => 'A', 'bobot' => 1, 'is_active' => true]);
        $this->assertSame(1, RubrikKriteria::query()->count());
        tenancy()->end();

        $this->boot('akpB');
        $this->assertSame(0, RubrikKriteria::query()->count(), 'Rubrik tenant lain tak boleh terlihat.');
        tenancy()->end();
    }
}
