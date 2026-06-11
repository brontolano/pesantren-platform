<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Modules\Kesantrian\Database\Seeders\JenisPelanggaranSeeder;
use Modules\Kesantrian\Models\EskalasiDisiplin;
use Modules\Kesantrian\Models\JenisPelanggaran;
use Modules\Kesantrian\Models\PelanggaranSantri;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\DisiplinNotifikasiService;
use Modules\Kesantrian\Services\DisiplinService;
use RuntimeException;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-09 — Modul disiplin/ta'zir berbasis poin (Blueprint Akademik A-4).
 *
 * (a) catat→verifikasi→poin terhitung; self-verify ditolak.
 * (b) saklar wajib_verifikasi=false → langsung diverifikasi.
 * (c) poin belum diverifikasi TIDAK terhitung.
 * (d) ambang terlampaui → SP1 tepat SEKALI (idempoten); SP2 di ambang berikut.
 * (e) ambang custom via Setting mengubah perilaku (policy-first).
 * (f) poin negatif prestasi mengurangi total; saklar poin_positif menutup pintu.
 * (g) batalkan (alasan wajib) mengembalikan poin.
 * (+) notifikasi wali ber-kebijakan; seeder idempoten; CHECK pgsql.
 */
class DisiplinTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'settings',
            'jenis_pelanggaran', 'pelanggaran_santri', 'sanksi_santri', 'eskalasi_disiplin',
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

    private function svc(): DisiplinService
    {
        return app(DisiplinService::class);
    }

    private function santri(string $nis = 'S1'): Santri
    {
        return Santri::create([
            'nis' => $nis, 'nama' => 'Santri '.$nis, 'tanggal_lahir' => '2011-01-01',
            'jenis_kelamin' => 'L', 'status' => 'aktif', 'no_hp_wali' => '081234567890',
        ]);
    }

    private function user(string $email): User
    {
        return User::withoutGlobalScopes()->create([
            'tenant_id' => tenant('id'), 'name' => 'User '.$email,
            'email' => $email, 'password' => 'password123',
        ]);
    }

    private function jenis(string $nama, string $kategori, int $poin): JenisPelanggaran
    {
        return JenisPelanggaran::create([
            'nama' => $nama, 'kategori' => $kategori, 'poin' => $poin, 'aktif' => true,
        ]);
    }

    // ---------------------------------------------------------------
    // (a) Alur verifikasi
    // ---------------------------------------------------------------

    public function test_catat_lalu_verifikasi_membuat_poin_terhitung_dan_self_verify_ditolak(): void
    {
        $this->bootTenant('dsva');
        $santri = $this->santri();
        $pencatat = $this->user('pencatat@t.test');
        $ustadz = $this->user('ustadz@t.test');
        $jenis = $this->jenis('Keluar tanpa izin', 'sedang', 25);

        $p = $this->svc()->catat($santri, $jenis, $pencatat, ['kronologi' => 'Keluar lewat pagar belakang.']);

        $this->assertSame('dicatat', $p->status, 'Saklar default wajib_verifikasi=true → status awal dicatat');
        $this->assertSame(0, $this->svc()->totalPoin($santri), 'Poin belum berlaku sebelum verifikasi');

        // Self-verify DITOLAK (anti bully pencatat berlapis).
        try {
            $this->svc()->verifikasi($p, $pencatat);
            $this->fail('Self-verify seharusnya ditolak');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sendiri', $e->getMessage());
        }
        $this->assertSame('dicatat', $p->fresh()->status);

        $this->svc()->verifikasi($p, $ustadz);

        $p->refresh();
        $this->assertSame('diverifikasi', $p->status);
        $this->assertSame($ustadz->id, $p->verifikator_id);
        $this->assertNotNull($p->verified_at);
        $this->assertSame(25, $this->svc()->totalPoin($santri));

        // Verifikasi dua kali → ditolak (hanya dari status 'dicatat').
        $this->expectException(RuntimeException::class);
        $this->svc()->verifikasi($p, $ustadz);
    }

    // ---------------------------------------------------------------
    // (b) Saklar wajib_verifikasi=false
    // ---------------------------------------------------------------

    public function test_saklar_wajib_verifikasi_false_langsung_diverifikasi(): void
    {
        $this->bootTenant('dsvb');
        Setting::create(['key' => DisiplinService::SETTING_WAJIB_VERIFIKASI, 'value' => false]);

        $santri = $this->santri();
        $ustadz = $this->user('ustadz@t.test');
        $jenis = $this->jenis('Terlambat sholat', 'ringan', 5);

        $p = $this->svc()->catat($santri, $jenis, $ustadz, ['kronologi' => 'Terlambat subuh.']);

        $this->assertSame('diverifikasi', $p->status, 'Saklar OFF → langsung diverifikasi');
        $this->assertSame($ustadz->id, $p->verifikator_id);
        $this->assertSame(5, $this->svc()->totalPoin($santri), 'Poin langsung terhitung');

        // Parsing toleran: string 'false' juga dikenali sebagai OFF.
        Setting::query()->where('key', DisiplinService::SETTING_WAJIB_VERIFIKASI)->update(['value' => json_encode('false')]);
        $p2 = $this->svc()->catat($santri, $jenis, $ustadz, ['kronologi' => 'Terlambat lagi.']);
        $this->assertSame('diverifikasi', $p2->status, "String 'false' harus dibaca OFF (parsing toleran)");
    }

    // ---------------------------------------------------------------
    // (c) Poin belum diverifikasi tidak terhitung
    // ---------------------------------------------------------------

    public function test_poin_belum_diverifikasi_tidak_terhitung(): void
    {
        $this->bootTenant('dsvc');
        $santri = $this->santri();
        $pencatat = $this->user('pengurus@t.test');
        $ustadz = $this->user('ustadz@t.test');
        $jenis = $this->jenis('Merokok', 'berat', 50);

        $p1 = $this->svc()->catat($santri, $jenis, $pencatat, ['kronologi' => 'Kejadian 1.']);
        $this->svc()->catat($santri, $jenis, $pencatat, ['kronologi' => 'Kejadian 2.']);

        $this->assertSame(0, $this->svc()->totalPoin($santri), 'Dua catatan belum diverifikasi → 0');

        $this->svc()->verifikasi($p1, $ustadz);
        $this->assertSame(50, $this->svc()->totalPoin($santri), 'Hanya yang diverifikasi yang terhitung');
    }

    // ---------------------------------------------------------------
    // (d) Eskalasi idempoten
    // ---------------------------------------------------------------

    public function test_eskalasi_sp1_tepat_sekali_dan_sp2_pada_ambang_berikut(): void
    {
        $this->bootTenant('dsvd');
        $santri = $this->santri();
        $pencatat = $this->user('pengurus@t.test');
        $ustadz = $this->user('ustadz@t.test');
        $jenis = $this->jenis('Merokok', 'berat', 50);
        $ringan = $this->jenis('Terlambat', 'ringan', 5);

        // 50 poin → SP1.
        $p1 = $this->svc()->catat($santri, $jenis, $pencatat, ['kronologi' => 'Kejadian 1.']);
        $this->svc()->verifikasi($p1, $ustadz);

        $this->assertSame(1, EskalasiDisiplin::query()->count());
        $sp1 = EskalasiDisiplin::query()->first();
        $this->assertSame('SP1', $sp1->label);
        $this->assertSame(50, $sp1->poin);

        // Poin bertambah (55) tapi masih di bawah SP2 → TIDAK ada SP1 kedua.
        $p2 = $this->svc()->catat($santri, $ringan, $pencatat, ['kronologi' => 'Kejadian 2.']);
        $this->svc()->verifikasi($p2, $ustadz);

        $this->assertSame(1, EskalasiDisiplin::query()->count(), 'SP1 tidak boleh dobel meski poin bertambah');

        // 105 poin → SP2 (sekali).
        $p3 = $this->svc()->catat($santri, $jenis, $pencatat, ['kronologi' => 'Kejadian 3.']);
        $this->svc()->verifikasi($p3, $ustadz);

        $labels = EskalasiDisiplin::query()->orderBy('poin')->pluck('label')->all();
        $this->assertSame(['SP1', 'SP2'], $labels);

        // Unique DB menolak duplikat label di periode sama (jaring kedua).
        $this->expectException(QueryException::class);
        EskalasiDisiplin::create([
            'santri_id' => $santri->id, 'periode' => $sp1->periode,
            'label' => 'SP1', 'poin' => 999, 'tanggal' => now()->toDateString(),
        ]);
    }

    // ---------------------------------------------------------------
    // (e) Ambang custom via Setting (policy-first)
    // ---------------------------------------------------------------

    public function test_ambang_custom_via_setting_mengubah_perilaku(): void
    {
        $this->bootTenant('dsve');
        Setting::create([
            'key' => DisiplinService::SETTING_AMBANG,
            'value' => [
                ['poin' => 20, 'label' => 'Pembinaan Ringan'],
                ['poin' => 40, 'label' => 'Panggil Wali'],
                ['entri' => 'liar tanpa poin/label — harus di-skip'],
            ],
        ]);

        $santri = $this->santri();
        $pencatat = $this->user('pengurus@t.test');
        $ustadz = $this->user('ustadz@t.test');
        $jenis = $this->jenis('HP tanpa izin', 'sedang', 20);

        $p1 = $this->svc()->catat($santri, $jenis, $pencatat, ['kronologi' => 'Kejadian 1.']);
        $this->svc()->verifikasi($p1, $ustadz);

        $this->assertSame(
            ['Pembinaan Ringan'],
            EskalasiDisiplin::query()->pluck('label')->all(),
            'Ambang 20 custom harus memicu eskalasi yang default (50) tidak akan memicu',
        );

        $p2 = $this->svc()->catat($santri, $jenis, $pencatat, ['kronologi' => 'Kejadian 2.']);
        $this->svc()->verifikasi($p2, $ustadz);

        $this->assertSame(
            ['Pembinaan Ringan', 'Panggil Wali'],
            EskalasiDisiplin::query()->orderBy('poin')->pluck('label')->all(),
        );

        // Setting rusak total → jatuh ke default SP1/SP2, bukan meledak.
        Setting::query()->where('key', DisiplinService::SETTING_AMBANG)->update(['value' => json_encode('bukan-array')]);
        $this->assertSame(DisiplinService::DEFAULT_AMBANG, $this->svc()->ambang());
    }

    // ---------------------------------------------------------------
    // (f) Poin positif / prestasi
    // ---------------------------------------------------------------

    public function test_poin_negatif_prestasi_mengurangi_total_dan_saklar_menutup(): void
    {
        $this->bootTenant('dsvf');
        $santri = $this->santri();
        $pencatat = $this->user('pengurus@t.test');
        $ustadz = $this->user('ustadz@t.test');
        $jenis = $this->jenis('HP tanpa izin', 'sedang', 20);
        $prestasi = $this->jenis('Juara lomba', 'prestasi', -25);

        $p1 = $this->svc()->catat($santri, $jenis, $pencatat, ['kronologi' => 'Kejadian.']);
        $this->svc()->verifikasi($p1, $ustadz);
        $this->assertSame(20, $this->svc()->totalPoin($santri));

        $p2 = $this->svc()->catat($santri, $prestasi, $ustadz, ['kronologi' => 'Juara 1 MQK kabupaten.']);
        $this->svc()->verifikasi($p2, $pencatat);
        $this->assertSame(-5, $this->svc()->totalPoin($santri), 'Prestasi -25 mengurangi total');
        $this->assertSame(0, EskalasiDisiplin::query()->count(), 'Prestasi tidak memicu eskalasi');

        // Saklar poin_positif OFF → pencatatan prestasi ditolak.
        Setting::create(['key' => DisiplinService::SETTING_POIN_POSITIF, 'value' => false]);
        $this->expectException(\InvalidArgumentException::class);
        $this->svc()->catat($santri, $prestasi, $ustadz, ['kronologi' => 'Lagi.']);
    }

    public function test_check_pgsql_prestasi_wajib_poin_negatif(): void
    {
        $this->bootTenant('dsck');

        $this->expectException(QueryException::class);
        $this->jenis('Prestasi salah arah', 'prestasi', 10);
    }

    // ---------------------------------------------------------------
    // (g) Pembatalan
    // ---------------------------------------------------------------

    public function test_batalkan_wajib_alasan_dan_mengembalikan_poin(): void
    {
        $this->bootTenant('dsvg');
        $santri = $this->santri();
        $pencatat = $this->user('pengurus@t.test');
        $ustadz = $this->user('ustadz@t.test');
        $jenis = $this->jenis('Ghosob', 'sedang', 20);

        $p = $this->svc()->catat($santri, $jenis, $pencatat, ['kronologi' => 'Memakai sandal kawan.']);
        $this->svc()->verifikasi($p, $ustadz);
        $this->assertSame(20, $this->svc()->totalPoin($santri));

        // Alasan kosong → ditolak.
        try {
            $this->svc()->batalkan($p->fresh(), $ustadz, '   ');
            $this->fail('Alasan kosong seharusnya ditolak');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('wajib', $e->getMessage());
        }

        $this->svc()->batalkan($p->fresh(), $ustadz, 'Salah identifikasi santri.');

        $p->refresh();
        $this->assertSame('dibatalkan', $p->status);
        $this->assertSame('Salah identifikasi santri.', $p->alasan_batal);
        $this->assertSame(0, $this->svc()->totalPoin($santri), 'Poin kembali setelah pembatalan');
    }

    // ---------------------------------------------------------------
    // Sanksi & notifikasi & seeder
    // ---------------------------------------------------------------

    public function test_sanksi_dicatat_dan_status_berubah(): void
    {
        $this->bootTenant('dssk');
        $santri = $this->santri();
        $pencatat = $this->user('pengurus@t.test');
        $ustadz = $this->user('ustadz@t.test');
        $jenis = $this->jenis('Keluar tanpa izin', 'sedang', 25);

        $p = $this->svc()->catat($santri, $jenis, $pencatat, ['kronologi' => 'Kabur.']);

        // Sanksi sebelum verifikasi → ditolak.
        try {
            $this->svc()->catatSanksi($p, $ustadz, ['jenis_sanksi' => 'Khidmah dapur']);
            $this->fail('Sanksi sebelum verifikasi seharusnya ditolak');
        } catch (RuntimeException) {
        }

        $this->svc()->verifikasi($p, $ustadz);
        $sanksi = $this->svc()->catatSanksi($p->fresh(), $ustadz, ['jenis_sanksi' => 'Khidmah dapur 3 hari']);

        $this->assertSame('sanksi_dijalankan', $p->fresh()->status);
        $this->assertSame($ustadz->id, $sanksi->pelaksana_id);
        $this->assertSame(25, $this->svc()->totalPoin($santri), 'Poin tetap berlaku saat sanksi dijalankan');

        $this->svc()->selesaikan($p->fresh());
        $this->assertSame('selesai', $p->fresh()->status);
        $this->assertSame(25, $this->svc()->totalPoin($santri), "Poin tetap berlaku pada status 'selesai'");
    }

    public function test_notifikasi_wali_mengikuti_kebijakan(): void
    {
        $this->bootTenant('dsnt');

        $notif = $this->createMock(DisiplinNotifikasiService::class);
        $this->app->instance(DisiplinNotifikasiService::class, $notif);

        $santri = $this->santri();
        $pencatat = $this->user('pengurus@t.test');
        $ustadz = $this->user('ustadz@t.test');
        $berat = $this->jenis('Merokok', 'berat', 50);

        // Default 'eskalasi': kejadian TIDAK dinotif, eskalasi DINOTIF.
        $notif->expects($this->never())->method('kejadian');
        $notif->expects($this->once())->method('eskalasi');

        $p = app(DisiplinService::class)->catat($santri, $berat, $pencatat, ['kronologi' => 'Merokok di belakang dapur.']);
        app(DisiplinService::class)->verifikasi($p, $ustadz); // 50 poin → SP1
    }

    public function test_notifikasi_mati_tidak_mengirim_apapun(): void
    {
        $this->bootTenant('dsnm');
        Setting::create(['key' => DisiplinService::SETTING_NOTIF_WALI, 'value' => 'mati']);

        $notif = $this->createMock(DisiplinNotifikasiService::class);
        $notif->expects($this->never())->method('kejadian');
        $notif->expects($this->never())->method('eskalasi');
        $this->app->instance(DisiplinNotifikasiService::class, $notif);

        $santri = $this->santri();
        $pencatat = $this->user('pengurus@t.test');
        $ustadz = $this->user('ustadz@t.test');
        $berat = $this->jenis('Merokok', 'berat', 50);

        $p = app(DisiplinService::class)->catat($santri, $berat, $pencatat, ['kronologi' => 'Kejadian.']);
        app(DisiplinService::class)->verifikasi($p, $ustadz);

        $this->assertSame(1, EskalasiDisiplin::query()->count(), 'Eskalasi tetap tercatat walau notifikasi mati');
    }

    public function test_seeder_jenis_pelanggaran_idempoten_dan_tidak_menimpa_editan(): void
    {
        $this->bootTenant('dssd');

        Artisan::call('db:seed', ['--class' => JenisPelanggaranSeeder::class, '--force' => true]);
        $jumlah = JenisPelanggaran::query()->count();
        $this->assertSame(count(JenisPelanggaranSeeder::contoh()), $jumlah);

        // Tenant mengubah poin merokok 50 → 75.
        $merokok = JenisPelanggaran::query()->where('nama', 'Merokok')->firstOrFail();
        $merokok->update(['poin' => 75]);

        Artisan::call('db:seed', ['--class' => JenisPelanggaranSeeder::class, '--force' => true]);

        $this->assertSame($jumlah, JenisPelanggaran::query()->count(), 'Reseed tidak menduplikasi');
        $this->assertSame(75, $merokok->fresh()->poin, 'Editan tenant tidak ditimpa');

        $this->assertTrue(
            JenisPelanggaran::query()->where('kategori', 'prestasi')->where('poin', '<', 0)->exists(),
            'Seeder menyertakan contoh prestasi berpoin negatif',
        );
    }

    public function test_status_liar_ditolak_check_constraint(): void
    {
        $this->bootTenant('dscs');
        $santri = $this->santri();
        $pencatat = $this->user('pengurus@t.test');
        $jenis = $this->jenis('Terlambat', 'ringan', 5);

        $this->expectException(QueryException::class);
        PelanggaranSantri::create([
            'santri_id' => $santri->id, 'jenis_pelanggaran_id' => $jenis->id,
            'waktu_kejadian' => now(), 'kronologi' => 'x',
            'pencatat_id' => $pencatat->id, 'status' => 'digantung',
        ]);
    }
}
