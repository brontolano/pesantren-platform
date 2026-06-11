<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Akademik\Models\Lembaga;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Services\KelulusanService;
use Modules\Akademik\Services\KenaikanKelasService;
use Modules\Kesantrian\Models\Alumni;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\PenempatanKelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Services\TagihanService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-08 — Kelulusan & Mutasi.
 *
 * (a) prosesLulus: alumni + status lulus + penempatan tertutup, TAPI riwayat
 *     nilai & penempatan utuh (baris tidak hilang).
 * (b) prosesLulus 2× idempoten — tidak ada duplikat alumni.
 * (c) Alumni tak tertagih SPP periode baru (generator filter status 'aktif').
 * (d) Nomor syahadah unik & berurutan + idempoten per alumni.
 * (e) Mutasi keluar: status + penempatan tertutup + riwayat utuh + jejak arsip.
 */
class KelulusanTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'settings', 'santri', 'kelas',
            'lembaga', 'periode_akademik', 'nilai', 'penempatan_kelas',
            'keputusan_kenaikan', 'alumni', 'status_santri', 'tagihan',
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

    private function svc(): KelulusanService
    {
        return app(KelulusanService::class);
    }

    /**
     * @return array{lembaga: Lembaga, kelas: Kelas, periode: PeriodeAkademik, user: User}
     */
    private function seedDasar(string $suffix): array
    {
        $lembaga = Lembaga::create(['nama' => 'MA '.$suffix, 'jenis' => 'formal']);

        $kelas = Kelas::create([
            'nama_kelas' => 'XII-'.$suffix, 'tingkat' => '12',
            'capacity' => 30, 'is_active' => true, 'lembaga_id' => $lembaga->id,
        ]);

        $periode = PeriodeAkademik::create([
            'nama' => 'Genap '.$suffix, 'tahun_akademik' => '2026/2027', 'semester' => 'genap',
            'tanggal_mulai' => '2027-01-01', 'tanggal_akhir' => '2027-06-30',
            'is_active' => true, 'lembaga_id' => $lembaga->id,
        ]);

        $user = User::create([
            'tenant_id' => tenant()->getTenantKey(), 'name' => 'Mudir '.$suffix,
            'email' => 'mudir-'.strtolower($suffix).'@wp08.test', 'password' => 'password123',
        ]);

        return compact('lembaga', 'kelas', 'periode', 'user');
    }

    private function santriDiKelas(string $nis, Kelas $kelas): Santri
    {
        $santri = Santri::create([
            'nis' => $nis, 'nama' => 'Santri '.$nis, 'tanggal_lahir' => '2010-01-01',
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

    /** Keputusan 'lulus' FINAL via mesin WP-07 (tanpa mengubah mesinnya). */
    private function finalkanLulus(Santri $santri, Kelas $kelas, PeriodeAkademik $periode, User $user): void
    {
        $kenaikan = app(KenaikanKelasService::class);
        $kenaikan->simpanDraft([
            'santri_id' => $santri->id,
            'periode_akademik_id' => $periode->id,
            'kelas_asal_id' => $kelas->id,
            'rekomendasi' => 'lulus',
            'keputusan' => 'lulus',
            'alasan' => [],
        ], $user);
        $kenaikan->finalisasi($periode, $kelas, $user);
    }

    // ---------------------------------------------------------------
    // (a) prosesLulus: alumni + status + penempatan tertutup, riwayat utuh
    // ---------------------------------------------------------------

    public function test_proses_lulus_membuat_alumni_dan_menutup_penempatan_tanpa_hapus_riwayat(): void
    {
        $this->bootTenant('wp08a');
        $d = $this->seedDasar('A');
        $santri = $this->santriDiKelas('LA1', $d['kelas']);
        $this->beriNilai($santri, $d['kelas'], $d['periode'], 90);
        $this->finalkanLulus($santri, $d['kelas'], $d['periode'], $d['user']);

        $jumlah = $this->svc()->prosesLulus($d['periode'], null, $d['user']);
        $this->assertSame(1, $jumlah);

        $santri->refresh();
        $this->assertSame('lulus', $santri->status);
        $this->assertNull($santri->kelas_id);

        // Alumni tercipta dengan lembaga & tahun lulus dari periode.
        $alumni = Alumni::query()->where('santri_id', $santri->id)->firstOrFail();
        $this->assertSame('2027', $alumni->tahun_lulus);
        $this->assertSame($d['lembaga']->id, $alumni->lembaga_id);

        // Jejak status_santri tercatat.
        $this->assertSame(1, DB::connection('tenant')->table('status_santri')
            ->where('santri_id', $santri->id)->where('status', 'lulus')->count());

        // Penempatan TERTUTUP tapi BARISNYA UTUH (riwayat tidak hilang).
        $this->assertSame(0, PenempatanKelas::query()
            ->where('santri_id', $santri->id)->where('is_current', true)->count());
        $this->assertSame(1, PenempatanKelas::query()->where('santri_id', $santri->id)->count());

        // Riwayat nilai utuh.
        $this->assertSame(1, Nilai::query()->where('santri_id', $santri->id)->count());
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // (b) Idempoten: prosesLulus 2× tidak menduplikasi alumni
    // ---------------------------------------------------------------

    public function test_proses_lulus_dua_kali_idempoten(): void
    {
        $this->bootTenant('wp08b');
        $d = $this->seedDasar('B');
        $santri = $this->santriDiKelas('LB1', $d['kelas']);
        $this->finalkanLulus($santri, $d['kelas'], $d['periode'], $d['user']);

        $this->assertSame(1, $this->svc()->prosesLulus($d['periode'], null, $d['user']));
        $this->assertSame(0, $this->svc()->prosesLulus($d['periode'], null, $d['user']));

        $this->assertSame(1, Alumni::query()->where('santri_id', $santri->id)->count());
        $this->assertSame(1, DB::connection('tenant')->table('status_santri')
            ->where('santri_id', $santri->id)->where('status', 'lulus')->count());
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // (c) Alumni tak tertagih SPP periode baru
    // ---------------------------------------------------------------

    public function test_alumni_tidak_tertagih_spp_periode_baru(): void
    {
        $this->bootTenant('wp08c');
        $d = $this->seedDasar('C');
        $lulusan = $this->santriDiKelas('LC1', $d['kelas']);
        $aktif = $this->santriDiKelas('LC2', $d['kelas']);

        $this->finalkanLulus($lulusan, $d['kelas'], $d['periode'], $d['user']);
        $this->svc()->prosesLulus($d['periode'], null, $d['user']);

        // Generator tagihan per kelas: hanya santri status 'aktif' di kelas.
        $records = (new TagihanService)->buatTagihanPerKelas($d['kelas'], [
            'jenis_tagihan' => 'spp',
            'nominal' => 250000,
            'jatuh_tempo' => '2027-07-10',
        ]);

        $this->assertCount(1, $records);
        $this->assertSame($aktif->id, $records->first()->santri_id);
        $this->assertSame(0, DB::connection('tenant')->table('tagihan')
            ->where('santri_id', $lulusan->id)->count());
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // (d) Nomor syahadah unik & berurutan (counter race-safe) + format Setting
    // ---------------------------------------------------------------

    public function test_nomor_syahadah_unik_berurutan_dan_idempoten(): void
    {
        $this->bootTenant('wp08d');
        $d = $this->seedDasar('D');

        $alumni = [];
        foreach (range(1, 5) as $i) {
            $santri = $this->santriDiKelas('LD'.$i, $d['kelas']);
            $this->finalkanLulus($santri, $d['kelas'], $d['periode'], $d['user']);
        }
        $this->svc()->prosesLulus($d['periode'], null, $d['user']);

        $semua = Alumni::query()->orderBy('id')->get();
        $this->assertCount(5, $semua);

        foreach ($semua as $a) {
            $this->svc()->generateNomorSyahadah($a);
        }

        $urut = Alumni::query()->orderBy('nomor_syahadah_urut')->pluck('nomor_syahadah_urut')->all();
        $this->assertSame([1, 2, 3, 4, 5], $urut); // berurutan tanpa lubang

        $nomor = Alumni::query()->pluck('nomor_syahadah');
        $this->assertSame(5, $nomor->unique()->count()); // unik semua
        $this->assertStringContainsString('/SYH/', (string) $nomor->first());
        $this->assertStringContainsString('/2027', (string) $nomor->first());

        // Idempoten: generate ulang tidak mengubah nomor & tidak menambah urut.
        $pertama = $semua->first()->fresh();
        $sebelum = $pertama->nomor_syahadah;
        $this->svc()->generateNomorSyahadah($pertama);
        $this->assertSame($sebelum, $pertama->fresh()->nomor_syahadah);
        $this->assertSame(5, (int) Alumni::query()->max('nomor_syahadah_urut'));

        // Format dari Setting dihormati.
        Setting::create(['key' => KelulusanService::SETTING_FORMAT_NOMOR, 'value' => 'SYH-{tahun}-{counter}']);
        $santri6 = $this->santriDiKelas('LD6', $d['kelas']);
        $this->finalkanLulus($santri6, $d['kelas'], $d['periode'], $d['user']);
        $this->svc()->prosesLulus($d['periode'], null, $d['user']);
        $a6 = Alumni::query()->where('santri_id', $santri6->id)->firstOrFail();
        $this->svc()->generateNomorSyahadah($a6);
        $this->assertSame('SYH-2027-006', $a6->fresh()->nomor_syahadah);

        // Render template memuat nomor & nama (template bawaan).
        $isi = $this->svc()->renderSyahadah($a6->fresh());
        $this->assertStringContainsString('SYH-2027-006', $isi);
        $this->assertStringContainsString('Santri LD6', $isi);
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // (e) Mutasi keluar: status + penempatan tertutup + riwayat utuh
    // ---------------------------------------------------------------

    public function test_mutasi_keluar_menutup_penempatan_dengan_riwayat_utuh(): void
    {
        $this->bootTenant('wp08e');
        $d = $this->seedDasar('E');
        $santri = $this->santriDiKelas('LE1', $d['kelas']);
        $this->beriNilai($santri, $d['kelas'], $d['periode'], 75);

        $hasil = $this->svc()->mutasiKeluar(
            $santri, '2027-03-01', 'Pindah ke PP Al-Hikmah', 'mutasi/surat-le1.pdf', $d['user'],
        );

        $this->assertSame('keluar', $hasil->status);
        $this->assertNull($hasil->kelas_id);

        // Jejak status + arsip path tersimpan (serve via /_tenant-storage).
        $jejak = DB::connection('tenant')->table('status_santri')
            ->where('santri_id', $santri->id)->where('status', 'keluar')->first();
        $this->assertNotNull($jejak);
        $this->assertStringContainsString('Pindah ke PP Al-Hikmah', $jejak->alasan);
        $this->assertSame('mutasi/surat-le1.pdf', $jejak->file_surat_path);

        // Penempatan tertutup, baris riwayat & nilai utuh.
        $this->assertSame(0, PenempatanKelas::query()
            ->where('santri_id', $santri->id)->where('is_current', true)->count());
        $this->assertSame(1, PenempatanKelas::query()->where('santri_id', $santri->id)->count());
        $this->assertSame(1, Nilai::query()->where('santri_id', $santri->id)->count());

        // Tidak jadi alumni; mutasi 2× ditolak.
        $this->assertSame(0, Alumni::query()->where('santri_id', $santri->id)->count());
        $this->expectException(\RuntimeException::class);
        $this->svc()->mutasiKeluar($santri->fresh(), '2027-03-02', 'dobel', null, $d['user']);
    }
}
