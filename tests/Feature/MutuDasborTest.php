<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mutu\MutuService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\PresensiSantri;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Mutu\Models\MutuLampu;
use Modules\Notifikasi\Database\Seeders\TemplateNotifikasiSeeder;
use Modules\Notifikasi\Models\BroadcastWa;
use Modules\Tahfidz\Models\HafalanTahfidz;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * 2a — Dasbor Mutu Santri (flagship penjaminan mutu UU 18/2019).
 *
 * (a) ambang custom via Setting mutu.ambang mengubah lampu (2 tenant beda → policy-first);
 * (b) santri pindah kelas → profil menyambung (agregat by santri_id, semua kelas periode itu);
 * (c) hafalan macet ≥3 pekan → lampu; ada setoran pekan ini → tidak;
 * (d) job mutu:hitung-lampu idempoten (2× run = baris mutu_lampu sama, notif 1×/hari);
 * (e) santri tanpa data apa pun → profil kosong ramah, bukan error.
 */
class MutuDasborTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'settings', 'santri', 'kelas', 'lembaga',
            'periode_akademik', 'nilai', 'presensi_santri', 'presensi_kegiatan', 'kegiatan',
            'presensi_asrama', 'tahfidz_hafalan', 'capaian_kitab', 'kunjungan_uks',
            'jenis_pelanggaran', 'pelanggaran_santri', 'kalender_akademik',
            'mutu_lampu', 'template_notifikasi', 'broadcast_wa', 'log_notifikasi',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function boot(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        return $tenant;
    }

    private function santri(string $nis = 'S1', ?int $kelasId = null): Santri
    {
        return Santri::create([
            'nis' => $nis.'-'.str()->random(4), 'nama' => 'Santri '.$nis,
            'tanggal_lahir' => '2011-01-01', 'jenis_kelamin' => 'L',
            'status' => 'aktif', 'no_hp_wali' => '081234567890', 'kelas_id' => $kelasId,
        ]);
    }

    private function periodeAktif(): PeriodeAkademik
    {
        return PeriodeAkademik::create([
            'nama' => 'Semester Uji', 'tahun_akademik' => '2025/2026', 'semester' => 'genap',
            'tanggal_mulai' => now()->subMonths(2)->toDateString(),
            'tanggal_akhir' => now()->addMonths(2)->toDateString(),
            'is_active' => true,
        ]);
    }

    private function setoran(Santri $santri, string $tanggalSelesai): HafalanTahfidz
    {
        return HafalanTahfidz::create([
            'santri_id' => $santri->id, 'surah' => 'Al-Baqarah',
            'ayat_awal' => 1, 'ayat_akhir' => 5, 'status' => 'selesai',
            'tanggal_selesai' => $tanggalSelesai,
        ]);
    }

    private function svc(): MutuService
    {
        return app(MutuService::class);
    }

    // ---------------------------------------------------------------
    // (a) Policy-first: ambang custom per tenant mengubah lampu
    // ---------------------------------------------------------------

    public function test_ambang_custom_per_tenant_mengubah_lampu(): void
    {
        // Tenant A: default (macet ≥3 pekan) → setoran 5 pekan lalu = LAMPU.
        $tenantA = $this->boot('mutua');
        $santriA = $this->santri('A1');
        $this->setoran($santriA, now()->subWeeks(5)->toDateString());

        $lampuA = $this->svc()->lampu($santriA);
        $this->assertContains('hafalan_macet', array_column($lampuA, 'jenis'),
            'Default ambang 3 pekan: setoran 5 pekan lalu harus menyalakan lampu');
        tenancy()->end();

        // Tenant B: ambang custom 10 pekan → kondisi sama TIDAK lampu (policy-first).
        $tenantB = $this->boot('mutub');
        Setting::create(['tenant_id' => $tenantB->id, 'key' => MutuService::SETTING_AMBANG,
            'value' => ['hafalan_macet_pekan' => 10]]);
        $santriB = $this->santri('B1');
        $this->setoran($santriB, now()->subWeeks(5)->toDateString());

        $lampuB = $this->svc()->lampu($santriB);
        $this->assertNotContains('hafalan_macet', array_column($lampuB, 'jenis'),
            'Tenant dengan ambang 10 pekan tidak boleh lampu pada macet 5 pekan');
    }

    public function test_parsing_ambang_toleran_terhadap_json_string_dan_sampah(): void
    {
        $this->boot('mutup');

        // String JSON sah → dipakai.
        Setting::create(['tenant_id' => tenant('id'), 'key' => MutuService::SETTING_AMBANG,
            'value' => json_encode(['alpa_per_bulan' => 7, 'hafalan_macet_pekan' => 'bukan-angka', 'nilai_turun' => -3])]);

        $ambang = $this->svc()->ambang();
        $this->assertSame(7, $ambang['alpa_per_bulan']);
        // Nilai rusak/negatif → fallback default, bukan error.
        $this->assertSame(MutuService::DEFAULT_AMBANG['hafalan_macet_pekan'], $ambang['hafalan_macet_pekan']);
        $this->assertSame(MutuService::DEFAULT_AMBANG['nilai_turun'], $ambang['nilai_turun']);
    }

    // ---------------------------------------------------------------
    // (b) Santri pindah kelas → profil menyambung
    // ---------------------------------------------------------------

    public function test_santri_pindah_kelas_profil_tetap_menyambung(): void
    {
        $this->boot('mutuk');
        $periode = $this->periodeAktif();
        $kelasA = Kelas::create(['nama_kelas' => '1A', 'tingkat' => '1', 'is_active' => true]);
        $kelasB = Kelas::create(['nama_kelas' => '1B', 'tingkat' => '1', 'is_active' => true]);
        $santri = $this->santri('P1', $kelasA->id);

        // Jejak di kelas A: 2 hadir + nilai 1 mapel.
        foreach ([10, 9] as $h) {
            PresensiSantri::create(['santri_id' => $santri->id, 'kelas_id' => $kelasA->id,
                'tanggal' => now()->subDays($h)->toDateString(), 'status' => 'hadir']);
        }
        $fikih = Pelajaran::create(['nama' => 'Fikih', 'kode' => 'FIK']);
        Nilai::create(['santri_id' => $santri->id, 'periode_akademik_id' => $periode->id,
            'pelajaran_id' => $fikih->id, 'kelas_id' => $kelasA->id, 'nilai_akhir' => 80]);

        // Pindah kelas → jejak di kelas B: 3 hadir + nilai 1 mapel.
        $santri->update(['kelas_id' => $kelasB->id]);
        foreach ([3, 2, 1] as $h) {
            PresensiSantri::create(['santri_id' => $santri->id, 'kelas_id' => $kelasB->id,
                'tanggal' => now()->subDays($h)->toDateString(), 'status' => 'hadir']);
        }
        $nahwu = Pelajaran::create(['nama' => 'Nahwu', 'kode' => 'NHW']);
        Nilai::create(['santri_id' => $santri->id, 'periode_akademik_id' => $periode->id,
            'pelajaran_id' => $nahwu->id, 'kelas_id' => $kelasB->id, 'nilai_akhir' => 70]);

        $profil = $this->svc()->profil($santri->fresh(), $periode);

        $this->assertSame(5, $profil['kehadiran']['kelas']['hadir'],
            'Hadir dari KEDUA kelas harus terhitung (agregat by santri_id, bukan kelas_id)');
        $this->assertSame(2, $profil['nilai']['jumlah_mapel']);
        $this->assertEqualsWithDelta(75.0, $profil['nilai']['rata'], 0.01);
    }

    // ---------------------------------------------------------------
    // (c) Hafalan macet
    // ---------------------------------------------------------------

    public function test_hafalan_macet_tiga_pekan_lampu_dan_setoran_pekan_ini_memadamkan(): void
    {
        $this->boot('mutuh');
        $santri = $this->santri('H1');

        // Setoran terakhir 4 pekan lalu → macet (default 3 pekan).
        $this->setoran($santri, now()->subWeeks(4)->toDateString());
        $lampu = $this->svc()->lampu($santri);
        $jenis = array_column($lampu, 'jenis');
        $this->assertContains('hafalan_macet', $jenis);

        // Ada setoran pekan ini → lampu padam.
        $this->setoran($santri, now()->toDateString());
        $lampu = $this->svc()->lampu($santri);
        $this->assertNotContains('hafalan_macet', array_column($lampu, 'jenis'));

        // Santri TANPA riwayat setoran sama sekali → bukan "macet" (tidak ada noise).
        $polos = $this->santri('H2');
        $this->assertNotContains('hafalan_macet', array_column($this->svc()->lampu($polos), 'jenis'));
    }

    // ---------------------------------------------------------------
    // (d) Job harian idempoten + notif tidak dobel di hari sama
    // ---------------------------------------------------------------

    public function test_job_hitung_lampu_idempoten_dan_notif_sekali_per_hari(): void
    {
        Http::fake();

        $tenant = $this->boot('mutuj');
        // Template notifikasi aktif (seeder produksi) + mustahiq ber-phone.
        $this->seed(TemplateNotifikasiSeeder::class);
        $mustahiq = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Mustahiq', 'phone' => '0899000111',
            'email' => 'mustahiq@'.$tenant->id.'.localhost', 'password' => 'password123',
        ]);
        $kelas = Kelas::create(['nama_kelas' => '2A', 'tingkat' => '2', 'is_active' => true, 'wali_kelas_id' => $mustahiq->id]);
        $santri = $this->santri('J1', $kelas->id);
        $this->setoran($santri, now()->subWeeks(6)->toDateString()); // macet → 1 lampu
        tenancy()->end();

        Artisan::call('mutu:hitung-lampu', ['tenant' => $tenant->id]);

        tenancy()->initialize($tenant);
        $this->assertSame(1, MutuLampu::query()->count());
        $row = MutuLampu::query()->firstOrFail();
        $this->assertSame('hafalan_macet', $row->jenis);
        $this->assertTrue($row->last_notified_at->isToday(), 'Notif harus ter-stamp hari ini');
        $broadcastSetelahRun1 = BroadcastWa::query()->count();
        $this->assertSame(1, $broadcastSetelahRun1, 'Run pertama mengirim tepat satu notifikasi');
        tenancy()->end();

        // Run kedua hari yang sama: baris cache identik, TIDAK ada notif baru.
        Artisan::call('mutu:hitung-lampu', ['tenant' => $tenant->id]);

        tenancy()->initialize($tenant);
        $this->assertSame(1, MutuLampu::query()->count(), 'Upsert by unique — tidak dobel baris');
        $this->assertSame($row->id, MutuLampu::query()->firstOrFail()->id);
        $this->assertSame($broadcastSetelahRun1, BroadcastWa::query()->count(),
            'Run kedua di hari sama tidak boleh kirim notif lagi (guard last_notified_at)');

        // Lampu padam (santri setor hari ini) → run berikut menghapus baris cache.
        $this->setoran(Santri::query()->findOrFail($santri->id), now()->toDateString());
        tenancy()->end();

        Artisan::call('mutu:hitung-lampu', ['tenant' => $tenant->id]);

        tenancy()->initialize($tenant);
        $this->assertSame(0, MutuLampu::query()->count(), 'Lampu padam harus dihapus dari cache');
    }

    // ---------------------------------------------------------------
    // (e) Santri tanpa data → profil kosong ramah
    // ---------------------------------------------------------------

    public function test_santri_tanpa_data_profil_kosong_ramah_bukan_error(): void
    {
        $this->boot('mutuo');
        $santri = $this->santri('K1'); // tanpa kelas, nilai, presensi, hafalan, apa pun

        $profil = $this->svc()->profil($santri);

        $this->assertNull($profil['nilai']['rata']);
        $this->assertSame(0, $profil['nilai']['jumlah_mapel']);
        $this->assertSame(0, array_sum(array_column($profil['hafalan']['per_pekan'], 'jumlah')));
        $this->assertNull($profil['hafalan']['terakhir']);
        $this->assertSame([], $profil['kitab']);
        $this->assertSame(0, $profil['kehadiran']['kelas']['hadir']);
        $this->assertSame([], $profil['kehadiran']['asrama']);
        $this->assertSame(0, $profil['disiplin']['total_poin']);
        $this->assertSame(0, $profil['uks']['jumlah']);
        $this->assertNull($profil['uks']['terakhir']);

        // Tanpa data & tanpa periode pun lampu tidak meledak.
        $this->assertSame([], $this->svc()->lampu($santri));
    }
}
