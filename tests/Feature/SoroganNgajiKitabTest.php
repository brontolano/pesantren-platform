<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Modules\Akademik\Models\Kitab;
use Modules\Akademik\Models\NgajiSesi;
use Modules\Akademik\Models\NgajiSesiPeserta;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\SetoranSorogan;
use Modules\Akademik\Services\RaporDiniyahService;
use Modules\Akademik\Services\SoroganService;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Issue #330 — Sorogan & Ngaji Kitab:
 * - CRUD setoran sorogan + nilai_rata (kelancaran/makna/nahwu).
 * - Sesi bandongan massal: 1 sesi + banyak peserta, tenant_id terisi.
 * - Progres per santri (SoroganService::progresPerKitab) + khatam.
 * - Ringkasan masuk blok 'sorogan' Rapor Diniyah; santri tanpa data →
 *   blok kosong ramah.
 */
class SoroganNgajiKitabTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'kitab',
            'sorogan_setoran', 'ngaji_sesi', 'ngaji_sesi_peserta',
            'periode_akademik', 'rapor_diniyah',
        ]);

        $this->truncatePpdbTables([
            'ngaji_sesi_peserta', 'ngaji_sesi', 'sorogan_setoran',
            'rapor_diniyah', 'periode_akademik', 'kitab', 'santri',
            'users', 'domains', 'tenants',
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
     * @return array{tenant: Tenant, santri: Santri, kitab: Kitab, ustadz: User}
     */
    private function fixture(string $prefix): array
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        $ustadz = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Ustadz Fulan',
            'email' => $prefix.'@sorogan.test',
            'password' => 'password123',
        ]);

        $santri = Santri::create([
            'tenant_id' => $tenant->id,
            'nis' => strtoupper($prefix).'-001',
            'nama' => 'Santri Sorogan',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);

        $kitab = Kitab::create([
            'tenant_id' => $tenant->id,
            'nama' => 'Fathul Qorib',
            'pengarang' => 'Ibnu Qasim al-Ghazzi',
            'kategori' => 'fiqih',
            'jumlah_halaman' => 120,
        ]);

        return compact('tenant', 'santri', 'kitab', 'ustadz');
    }

    public function test_crud_setoran_sorogan_dan_nilai_rata(): void
    {
        $fx = $this->fixture('sor');

        $setoran = SetoranSorogan::create([
            'tenant_id' => $fx['tenant']->id,
            'santri_id' => $fx['santri']->id,
            'kitab_id' => $fx['kitab']->id,
            'bab' => 'Bab Thaharah',
            'halaman_dari' => 3,
            'halaman_sampai' => 5,
            'tanggal' => '2026-02-01',
            'ustadz_id' => $fx['ustadz']->id,
            'metode' => 'sorogan',
            'nilai_kelancaran' => 80,
            'nilai_makna' => 90,
            'nilai_nahwu' => 70,
            'status' => 'lanjut',
            'catatan' => "I'rab mubtada masih perlu diulang.",
        ]);

        $this->assertSame(80.0, (float) $setoran->nilai_rata);
        $this->assertSame('Fathul Qorib', $setoran->kitab->nama);
        $this->assertSame('Ustadz Fulan', $setoran->ustadz->name);

        // Update + nilai_rata mengabaikan yang kosong.
        $setoran->update(['nilai_nahwu' => null, 'status' => 'ulang']);
        $this->assertSame(85.0, (float) $setoran->fresh()->nilai_rata);

        // Belum dinilai sama sekali → null (bukan 0).
        $kosong = SetoranSorogan::create([
            'tenant_id' => $fx['tenant']->id,
            'santri_id' => $fx['santri']->id,
            'kitab_id' => $fx['kitab']->id,
            'tanggal' => '2026-02-02',
        ]);
        $this->assertNull($kosong->nilai_rata);

        $setoran->delete();
        $this->assertDatabaseMissing('sorogan_setoran', ['id' => $setoran->id]);
    }

    public function test_sesi_bandongan_massal_banyak_peserta(): void
    {
        $fx = $this->fixture('ban');

        $santri2 = Santri::create([
            'tenant_id' => $fx['tenant']->id,
            'nis' => 'BAN-002',
            'nama' => 'Santri Kedua',
            'tanggal_lahir' => '2012-05-05',
            'jenis_kelamin' => 'P',
            'status' => 'aktif',
        ]);

        $sesi = NgajiSesi::create([
            'tenant_id' => $fx['tenant']->id,
            'kitab_id' => $fx['kitab']->id,
            'bab' => 'Bab Muamalah',
            'halaman_dari' => 40,
            'halaman_sampai' => 42,
            'tanggal' => '2026-02-10',
            'ustadz_id' => $fx['ustadz']->id,
            'metode' => 'bandongan',
        ]);

        foreach ([[$fx['santri']->id, true, 85], [$santri2->id, false, null]] as [$sid, $hadir, $nilai]) {
            NgajiSesiPeserta::create([
                'ngaji_sesi_id' => $sesi->id,
                'santri_id' => $sid,
                'hadir' => $hadir,
                'nilai' => $nilai,
            ]);
        }

        $this->assertSame(2, $sesi->peserta()->count());
        // tenant_id WAJIB terisi via BelongsToTenant (jebakan attach pivot).
        $this->assertSame(
            $fx['tenant']->id,
            $sesi->peserta()->first()->tenant_id,
        );
        $this->assertSame(1, $sesi->peserta()->where('hadir', true)->count());
    }

    public function test_progres_per_santri_dan_khatam(): void
    {
        $fx = $this->fixture('pro');

        foreach ([
            ['tanggal' => '2026-01-05', 'halaman_dari' => 1, 'halaman_sampai' => 3, 'status' => 'lanjut', 'nilai' => 70],
            ['tanggal' => '2026-01-12', 'halaman_dari' => 4, 'halaman_sampai' => 6, 'status' => 'ulang', 'nilai' => 60],
            ['tanggal' => '2026-01-19', 'halaman_dari' => 118, 'halaman_sampai' => 120, 'status' => 'khatam', 'nilai' => 90],
        ] as $row) {
            SetoranSorogan::create([
                'tenant_id' => $fx['tenant']->id,
                'santri_id' => $fx['santri']->id,
                'kitab_id' => $fx['kitab']->id,
                'bab' => 'Bab',
                'halaman_dari' => $row['halaman_dari'],
                'halaman_sampai' => $row['halaman_sampai'],
                'tanggal' => $row['tanggal'],
                'metode' => 'sorogan',
                'nilai_kelancaran' => $row['nilai'],
                'nilai_makna' => $row['nilai'],
                'nilai_nahwu' => $row['nilai'],
                'status' => $row['status'],
            ]);
        }

        $progres = app(SoroganService::class)->progresPerKitab($fx['santri']);

        $this->assertCount(1, $progres);
        $this->assertSame('Fathul Qorib', $progres[0]['kitab']);
        $this->assertSame(3, $progres[0]['jumlah_setoran']);
        $this->assertTrue($progres[0]['khatam']);
        $this->assertSame('khatam', $progres[0]['status_terakhir']);
        $this->assertSame('Bab hal. 118–120', $progres[0]['posisi_terakhir']);
        $this->assertEqualsWithDelta(73.33, $progres[0]['nilai_rata'], 0.01);
    }

    public function test_ringkasan_sorogan_masuk_rapor_diniyah(): void
    {
        $fx = $this->fixture('rap');

        $periode = PeriodeAkademik::create([
            'tenant_id' => $fx['tenant']->id,
            'nama' => 'Semester Ganjil',
            'tahun_akademik' => '2026/2027',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_akhir' => '2026-06-30',
            'is_active' => true,
        ]);

        SetoranSorogan::create([
            'tenant_id' => $fx['tenant']->id,
            'santri_id' => $fx['santri']->id,
            'kitab_id' => $fx['kitab']->id,
            'tanggal' => '2026-02-01',
            'metode' => 'sorogan',
            'nilai_kelancaran' => 80,
            'nilai_makna' => 80,
            'nilai_nahwu' => 80,
            'status' => 'khatam',
        ]);

        // Di luar periode → TIDAK ikut dihitung.
        SetoranSorogan::create([
            'tenant_id' => $fx['tenant']->id,
            'santri_id' => $fx['santri']->id,
            'kitab_id' => $fx['kitab']->id,
            'tanggal' => '2025-11-01',
            'metode' => 'sorogan',
            'status' => 'lanjut',
        ]);

        $sesi = NgajiSesi::create([
            'tenant_id' => $fx['tenant']->id,
            'kitab_id' => $fx['kitab']->id,
            'tanggal' => '2026-03-01',
            'metode' => 'halaqah',
        ]);
        NgajiSesiPeserta::create([
            'ngaji_sesi_id' => $sesi->id,
            'santri_id' => $fx['santri']->id,
            'hadir' => true,
        ]);

        $rapor = app(RaporDiniyahService::class)->generate($fx['santri'], $periode);

        $blok = $rapor->blok['sorogan'];
        $this->assertSame(1, $blok['jumlah_setoran']);
        $this->assertSame(1, $blok['kitab_khatam']);
        $this->assertSame(80.0, (float) $blok['nilai_rata']);
        $this->assertSame(['ikut' => 1, 'hadir' => 1], $blok['sesi_bandongan']);

        // Santri tanpa data → blok kosong ramah (bukan error).
        $santriBaru = Santri::create([
            'tenant_id' => $fx['tenant']->id,
            'nis' => 'RAP-002',
            'nama' => 'Santri Kosong',
            'tanggal_lahir' => '2013-01-01',
            'jenis_kelamin' => 'P',
            'status' => 'aktif',
        ]);

        $raporKosong = app(RaporDiniyahService::class)->generate($santriBaru, $periode);
        $blokKosong = $raporKosong->blok['sorogan'];
        $this->assertSame([], $blokKosong['daftar']);
        $this->assertSame(0, $blokKosong['jumlah_setoran']);
        $this->assertNull($blokKosong['nilai_rata']);
    }
}
