<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Modules\Akademik\Database\Seeders\KalenderLiburNasionalSeeder;
use Modules\Akademik\Models\KalenderAkademik;
use Modules\Akademik\Models\Lembaga;
use Modules\Akademik\Services\KalenderService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-02 — Kalender akademik + hari efektif (Blueprint Akademik A-0 poin 3).
 *
 * (a) isHariEfektif: libur pondok → false; ujian → true; libur lembaga A
 *     tidak mempengaruhi lembaga B; entri NULL-lembaga mempengaruhi semua;
 *     skenario blueprint: Ramadhan diniyah → diniyah tak efektif, formal tetap.
 * (b) hariEfektifDalamRentang: 14 hari + 2 hari libur + default Minggu;
 *     kebijakan hari_libur_mingguan = ['jumat'] → hasil BEDA (policy-first).
 * (c) Seeder libur nasional idempoten + tidak menimpa entri yang diedit.
 * (d) CHECK pgsql: jenis liar & rentang terbalik ditolak.
 *
 * Pola baku: createRuntimeTenant (LembagaFondasiTest).
 */
class KalenderAkademikTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'lembaga', 'kalender_akademik', 'settings',
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

    private function svc(): KalenderService
    {
        return app(KalenderService::class);
    }

    // ---------------------------------------------------------------
    // (a) isHariEfektif
    // ---------------------------------------------------------------

    public function test_libur_pondok_membuat_hari_tidak_efektif_ujian_tidak(): void
    {
        $this->bootTenant('klhe');

        KalenderAkademik::create([
            'tanggal_mulai' => '2026-08-03', 'tanggal_selesai' => '2026-08-05',
            'jenis' => 'libur_pondok', 'keterangan' => 'Haflah akhir tahun',
        ]);
        KalenderAkademik::create([
            'tanggal_mulai' => '2026-08-10', 'tanggal_selesai' => '2026-08-12',
            'jenis' => 'ujian', 'keterangan' => 'Ujian semester',
        ]);

        $svc = $this->svc();

        $this->assertFalse($svc->isHariEfektif(Carbon::parse('2026-08-03')), 'Hari pertama libur pondok tak efektif');
        $this->assertFalse($svc->isHariEfektif(Carbon::parse('2026-08-05')), 'Hari terakhir libur pondok tak efektif (inklusif)');
        $this->assertTrue($svc->isHariEfektif(Carbon::parse('2026-08-06')), 'Sehari setelah libur kembali efektif');
        $this->assertTrue($svc->isHariEfektif(Carbon::parse('2026-08-11')), 'Hari UJIAN tetap hari efektif (tidak mengurangi)');
    }

    public function test_libur_lembaga_a_tidak_mempengaruhi_lembaga_b_dan_null_mempengaruhi_semua(): void
    {
        $this->bootTenant('kllb');

        $diniyah = Lembaga::create(['nama' => 'Madin', 'jenis' => 'diniyah']);
        $formal = Lembaga::create(['nama' => 'MTs', 'jenis' => 'formal']);

        // Skenario blueprint: Ramadhan ditandai DINIYAH saja — formal jalan terus.
        KalenderAkademik::create([
            'tanggal_mulai' => '2026-02-18', 'tanggal_selesai' => '2026-03-19',
            'jenis' => 'ramadhan', 'keterangan' => 'Libur Ramadhan 1447 H',
            'lembaga_id' => $diniyah->id,
        ]);

        $svc = $this->svc();
        $tanggal = Carbon::parse('2026-03-02');

        $this->assertFalse($svc->isHariEfektif($tanggal, $diniyah), 'Ramadhan diniyah → tak efektif utk diniyah');
        $this->assertTrue($svc->isHariEfektif($tanggal, $formal), 'Formal TIDAK menandai Ramadhan → tetap efektif');
        $this->assertTrue($svc->isHariEfektif($tanggal), 'Tanpa lembaga (umum) — entri ber-lembaga tidak berlaku');

        // Entri NULL-lembaga berlaku untuk SEMUA lembaga.
        KalenderAkademik::create([
            'tanggal_mulai' => '2026-08-17', 'tanggal_selesai' => '2026-08-17',
            'jenis' => 'libur_nasional', 'keterangan' => 'HUT RI',
        ]);

        $hutRi = Carbon::parse('2026-08-17');
        $this->assertFalse($svc->isHariEfektif($hutRi, $diniyah));
        $this->assertFalse($svc->isHariEfektif($hutRi, $formal));
        $this->assertFalse($svc->isHariEfektif($hutRi));
    }

    // ---------------------------------------------------------------
    // (b) hariEfektifDalamRentang — policy-first hari libur mingguan
    // ---------------------------------------------------------------

    public function test_hari_efektif_dalam_rentang_default_minggu(): void
    {
        $this->bootTenant('klrg');

        // Senin 2026-08-03 s/d Minggu 2026-08-16 = 14 hari.
        // Minggu: 9 & 16. Libur pondok: 9-10 (tumpang tindih Minggu 9).
        // Tak efektif = {9, 10, 16} → efektif = 14 - 3 = 11.
        KalenderAkademik::create([
            'tanggal_mulai' => '2026-08-09', 'tanggal_selesai' => '2026-08-10',
            'jenis' => 'libur_pondok', 'keterangan' => 'Haul masyayikh',
        ]);
        // Entri ujian TIDAK boleh mengurangi.
        KalenderAkademik::create([
            'tanggal_mulai' => '2026-08-12', 'tanggal_selesai' => '2026-08-13',
            'jenis' => 'ujian', 'keterangan' => 'Ujian tengah semester',
        ]);

        $hasil = $this->svc()->hariEfektifDalamRentang(
            Carbon::parse('2026-08-03'), Carbon::parse('2026-08-16'),
        );

        $this->assertSame(11, $hasil);
    }

    public function test_kebijakan_libur_jumat_menghasilkan_hitungan_beda(): void
    {
        $this->bootTenant('kljm');

        // Rentang & libur SAMA dgn test sebelumnya, kebijakan beda:
        // Jumat: 7 & 14. Libur pondok 9-10. Tak efektif = {7, 9, 10, 14}
        // → efektif = 14 - 4 = 10 ≠ 11 (bukti tombol kebijakan bekerja).
        Setting::create([
            'key' => KalenderService::SETTING_HARI_LIBUR_MINGGUAN,
            'value' => ['jumat'],
        ]);

        KalenderAkademik::create([
            'tanggal_mulai' => '2026-08-09', 'tanggal_selesai' => '2026-08-10',
            'jenis' => 'libur_pondok', 'keterangan' => 'Haul masyayikh',
        ]);

        $hasil = $this->svc()->hariEfektifDalamRentang(
            Carbon::parse('2026-08-03'), Carbon::parse('2026-08-16'),
        );

        $this->assertSame(10, $hasil, 'Pesantren libur Jumat harus menghasilkan hitungan BERBEDA dari default Minggu');
    }

    public function test_rentang_terbalik_mengembalikan_nol(): void
    {
        $this->bootTenant('klrt');

        $hasil = $this->svc()->hariEfektifDalamRentang(
            Carbon::parse('2026-08-16'), Carbon::parse('2026-08-03'),
        );

        $this->assertSame(0, $hasil, 'dari > sampai harus 0, bukan exception/negatif');
    }

    public function test_entri_menjangkau_melewati_batas_rentang_tetap_terhitung(): void
    {
        $this->bootTenant('klsp');

        // Libur 2026-07-30 s/d 2026-08-20 menelan SELURUH rentang
        // Sen 3 - Sab 8 Agu (mulai sebelum, selesai sesudah rentang).
        KalenderAkademik::create([
            'tanggal_mulai' => '2026-07-30', 'tanggal_selesai' => '2026-08-20',
            'jenis' => 'libur_pondok', 'keterangan' => 'Libur panjang',
        ]);

        $hasil = $this->svc()->hariEfektifDalamRentang(
            Carbon::parse('2026-08-03'), Carbon::parse('2026-08-08'),
        );

        $this->assertSame(0, $hasil, 'Entri yang menjangkau melewati batas rentang harus tetap mengurangi');
    }

    public function test_kebijakan_libur_mingguan_kosong_berarti_tanpa_libur_mingguan(): void
    {
        $this->bootTenant('klks');

        Setting::create([
            'key' => KalenderService::SETTING_HARI_LIBUR_MINGGUAN,
            'value' => [],
        ]);

        // Sen 3 - Minggu 16 Agu = 14 hari, tanpa libur apa pun → 14 penuh.
        $hasil = $this->svc()->hariEfektifDalamRentang(
            Carbon::parse('2026-08-03'), Carbon::parse('2026-08-16'),
        );

        $this->assertSame(14, $hasil, '[] = kebijakan sah tanpa libur mingguan');
    }

    public function test_kebijakan_string_dan_kapital_tetap_dikenali_typo_jatuh_ke_default(): void
    {
        $this->bootTenant('klst');

        // String tunggal kapital (bukan array) — harus tetap dikenali.
        Setting::create([
            'key' => KalenderService::SETTING_HARI_LIBUR_MINGGUAN,
            'value' => 'Jumat',
        ]);

        $hasil = $this->svc()->hariEfektifDalamRentang(
            Carbon::parse('2026-08-03'), Carbon::parse('2026-08-16'),
        );
        $this->assertSame(12, $hasil, "String 'Jumat' harus dibaca = libur Jumat (2 Jumat dalam 14 hari)");

        // Typo semua entri → jatuh ke default Minggu, BUKAN tanpa-libur.
        Setting::query()->where('key', KalenderService::SETTING_HARI_LIBUR_MINGGUAN)
            ->update(['value' => json_encode(['jumaat'])]);

        $hasil = $this->svc()->hariEfektifDalamRentang(
            Carbon::parse('2026-08-03'), Carbon::parse('2026-08-16'),
        );
        $this->assertSame(12, $hasil, 'Typo total harus jatuh ke default Minggu (2 Minggu), bukan 14');
    }

    public function test_rentang_per_lembaga_hanya_kurangi_lembaga_yang_menandai(): void
    {
        $this->bootTenant('klrl');

        $diniyah = Lembaga::create(['nama' => 'Madin', 'jenis' => 'diniyah']);
        $formal = Lembaga::create(['nama' => 'MTs', 'jenis' => 'formal']);

        // Libur diniyah Sen-Sel 3-4 Agu. Rentang Sen 3 - Sab 8 (6 hari, tanpa Minggu).
        KalenderAkademik::create([
            'tanggal_mulai' => '2026-08-03', 'tanggal_selesai' => '2026-08-04',
            'jenis' => 'libur_pondok', 'keterangan' => 'Libur khusus madin',
            'lembaga_id' => $diniyah->id,
        ]);

        $svc = $this->svc();
        $dari = Carbon::parse('2026-08-03');
        $sampai = Carbon::parse('2026-08-08');

        $this->assertSame(4, $svc->hariEfektifDalamRentang($dari, $sampai, $diniyah));
        $this->assertSame(6, $svc->hariEfektifDalamRentang($dari, $sampai, $formal));
    }

    // ---------------------------------------------------------------
    // (c) Seeder libur nasional idempoten
    // ---------------------------------------------------------------

    public function test_seeder_libur_nasional_idempoten_dan_tidak_menimpa_editan(): void
    {
        $this->bootTenant('klsd');

        Artisan::call('db:seed', ['--class' => KalenderLiburNasionalSeeder::class, '--force' => true]);
        $jumlah = KalenderAkademik::query()->count();
        $this->assertSame(count(KalenderLiburNasionalSeeder::liburNasional2026()), $jumlah);

        // Tenant mengedit satu entri (perkiraan Hijriyah digeser).
        $idulFitri = KalenderAkademik::query()->where('keterangan', 'like', '%Idul Fitri%')->firstOrFail();
        $idulFitri->update(['tanggal_selesai' => '2026-03-23']);

        Artisan::call('db:seed', ['--class' => KalenderLiburNasionalSeeder::class, '--force' => true]);

        $this->assertSame($jumlah, KalenderAkademik::query()->count(), 'Seeder ulang tidak boleh menambah baris');
        $this->assertSame('2026-03-23', $idulFitri->fresh()->tanggal_selesai->toDateString(), 'Editan tenant tidak boleh ditimpa seeder ulang');

        // Tenant menggeser TANGGAL_MULAI (kasus paling mungkin: perkiraan
        // Hijriyah meleset) — reseed TIDAK boleh menghidupkan entri lama.
        $maulid = KalenderAkademik::query()->where('keterangan', 'like', '%Maulid%')->firstOrFail();
        $maulid->update(['tanggal_mulai' => '2026-08-26', 'tanggal_selesai' => '2026-08-26']);

        Artisan::call('db:seed', ['--class' => KalenderLiburNasionalSeeder::class, '--force' => true]);

        $this->assertSame($jumlah, KalenderAkademik::query()->count(), 'Geser tanggal_mulai + reseed tidak boleh menduplikasi entri');
        $this->assertSame('2026-08-26', $maulid->fresh()->tanggal_mulai->toDateString());

        $this->assertTrue(
            KalenderAkademik::query()->where('jenis', '!=', 'libur_nasional')->doesntExist(),
            'Semua entri seeder berjenis libur_nasional',
        );
        $this->assertTrue(
            KalenderAkademik::query()->whereNotNull('lembaga_id')->doesntExist(),
            'Entri seeder NULL-lembaga (berlaku semua lembaga)',
        );
    }

    // ---------------------------------------------------------------
    // (d) CHECK pgsql
    // ---------------------------------------------------------------

    public function test_jenis_di_luar_daftar_ditolak_check_constraint(): void
    {
        $this->bootTenant('klck');

        $this->expectException(QueryException::class);

        KalenderAkademik::create([
            'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-08-01',
            'jenis' => 'rebahan_massal', 'keterangan' => 'Aneh',
        ]);
    }

    public function test_tanggal_selesai_sebelum_mulai_ditolak_check_constraint(): void
    {
        $this->bootTenant('klcr');

        $this->expectException(QueryException::class);

        KalenderAkademik::create([
            'tanggal_mulai' => '2026-08-10', 'tanggal_selesai' => '2026-08-09',
            'jenis' => 'kegiatan', 'keterangan' => 'Rentang terbalik',
        ]);
    }
}
