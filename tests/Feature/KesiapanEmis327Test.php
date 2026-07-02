<?php

namespace Tests\Feature;

use App\Filament\Pages\KesiapanEmis;
use Modules\Akademik\Models\Lembaga;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Services\EmisExportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Refs #327 — Kesiapan EMIS diperluas:
 * (a) rekap semua field wajib EMIS 4.0 (NIK/NISN/KK/akta/TTL/alamat/ortu/
 *     mukim/rombel) + hitungan santri lengkap + persen keseluruhan;
 * (b) rincian per lembaga (santri → kelas → lembaga);
 * (c) ekspor santri membawa kolom baru NO_KK..JENJANG (jenjang dari Lembaga).
 */
class KesiapanEmis327Test extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'santri', 'kelas', 'employee', 'lembaga', 'tagihan',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    /** @return array<string, mixed> Atribut santri LENGKAP semua syarat EMIS. */
    private function atributLengkap(Kelas $kelas): array
    {
        return [
            'status' => 'aktif', 'jenis_kelamin' => 'L',
            'nik' => '3211000000000001', 'nisn' => '0051234567',
            'no_kk' => '3211000000000090', 'no_akta_lahir' => 'AL-1/2012',
            'tempat_lahir' => 'Sumedang', 'tanggal_lahir' => '2012-01-01',
            'alamat' => 'Jl. Pesantren 1',
            'nama_ayah' => 'Fulan', 'nama_ibu' => 'Fulanah',
            'is_mukim' => true, 'kelas_id' => $kelas->id,
        ];
    }

    public function test_rekap_field_emis_lengkap_dan_persen_keseluruhan(): void
    {
        $this->boot('k327a');

        $lembaga = Lembaga::create(['tenant_id' => tenant('id'), 'nama' => 'MTs Uji', 'jenis' => 'formal', 'jenjang' => 'MTs']);
        $kelas = Kelas::create(['tenant_id' => tenant('id'), 'nama_kelas' => '7A', 'tingkat' => '7', 'lembaga_id' => $lembaga->id]);

        Santri::create(['nis' => 'L1', 'nama' => 'Lengkap'] + $this->atributLengkap($kelas));
        // Kurang: tanpa KK, akta, ortu, mukim, rombel.
        Santri::create([
            'nis' => 'K1', 'nama' => 'Kurang', 'status' => 'aktif', 'jenis_kelamin' => 'P',
            'nik' => '3211000000000002', 'nisn' => '0051234568',
            'tempat_lahir' => 'Sumedang', 'tanggal_lahir' => '2012-02-02', 'alamat' => 'Jl. 2',
        ]);
        // Non-aktif: tidak dihitung.
        Santri::create(['nis' => 'X1', 'nama' => 'Alumni', 'status' => 'lulus', 'jenis_kelamin' => 'L', 'tanggal_lahir' => '2010-01-01']);

        $stat = KesiapanEmis::hitungStatistik();

        $this->assertSame(2, $stat['santri']['total']);
        $this->assertSame(1, $stat['santri']['lengkap']);
        $this->assertSame(50.0, $stat['santri']['persen_lengkap']);

        $f = $stat['santri']['fields'];
        $this->assertSame(2, $f['nik']['isi']);
        $this->assertSame(1, $f['no_kk']['isi']);
        $this->assertSame(1, $f['no_akta_lahir']['isi']);
        $this->assertSame(2, $f['ttl']['isi']);
        $this->assertSame(1, $f['ortu']['isi']);
        $this->assertSame(1, $f['mukim']['isi']);
        $this->assertSame(1, $f['rombel']['isi']);

        // Kunci lama WP-12 tetap ada (kompatibilitas).
        $this->assertSame(2, $stat['santri']['ber_nik']);
        $this->assertSame(100.0, $stat['santri']['persen_nisn']);

        // Keseluruhan: santri 2×9 sel = 18, terisi 9 (Lengkap) + 4 (Kurang:
        // NIK/NISN/TTL/alamat) = 13; tanpa pegawai; lembaga 1 tanpa legalitas
        // → +0/1. Total 13/19.
        $this->assertSame(19, $stat['keseluruhan']['sel_total']);
        $this->assertSame(13, $stat['keseluruhan']['sel_terisi']);
        $this->assertEqualsWithDelta(68.4, $stat['keseluruhan']['persen'], 0.05);
        tenancy()->end();
    }

    public function test_rincian_per_lembaga(): void
    {
        $this->boot('k327b');

        $mts = Lembaga::create(['tenant_id' => tenant('id'), 'nama' => 'MTs', 'jenis' => 'formal', 'jenjang' => 'MTs']);
        $ma = Lembaga::create(['tenant_id' => tenant('id'), 'nama' => 'MA', 'jenis' => 'formal', 'jenjang' => 'MA']);
        $k7 = Kelas::create(['tenant_id' => tenant('id'), 'nama_kelas' => '7A', 'tingkat' => '7', 'lembaga_id' => $mts->id]);
        $k10 = Kelas::create(['tenant_id' => tenant('id'), 'nama_kelas' => '10A', 'tingkat' => '10', 'lembaga_id' => $ma->id]);

        Santri::create(['nis' => 'A1', 'nama' => 'A'] + $this->atributLengkap($k7));
        Santri::create(['nis' => 'A2', 'nama' => 'B', 'status' => 'aktif', 'jenis_kelamin' => 'L', 'tanggal_lahir' => '2012-05-05', 'kelas_id' => $k7->id]);
        Santri::create(['nis' => 'B1', 'nama' => 'C', 'status' => 'aktif', 'jenis_kelamin' => 'P', 'tanggal_lahir' => '2009-05-05', 'kelas_id' => $k10->id]);
        Santri::create(['nis' => 'C1', 'nama' => 'D', 'status' => 'aktif', 'jenis_kelamin' => 'L', 'tanggal_lahir' => '2011-05-05']); // tanpa rombel

        $per = collect(KesiapanEmis::hitungStatistik()['per_lembaga'])->keyBy('nama');

        $this->assertSame(2, $per['MTs']['total']);
        $this->assertSame(1, $per['MTs']['lengkap']);
        $this->assertSame(1, $per['MTs']['tanpa_nik']);
        $this->assertSame(1, $per['MA']['total']);
        $this->assertSame(0, $per['MA']['lengkap']);
        $this->assertSame(1, $per['Belum punya rombel']['total']);
        tenancy()->end();
    }

    public function test_ekspor_santri_membawa_kolom_rombel_lembaga_jenjang(): void
    {
        $this->boot('k327c');

        $lembaga = Lembaga::create(['tenant_id' => tenant('id'), 'nama' => 'MTs Uji', 'jenis' => 'formal', 'jenjang' => 'MTs']);
        $kelas = Kelas::create(['tenant_id' => tenant('id'), 'nama_kelas' => '7A', 'tingkat' => '7', 'lembaga_id' => $lembaga->id]);
        Santri::create(['nis' => 'E1', 'nama' => 'Ekspor'] + $this->atributLengkap($kelas));

        $svc = app(EmisExportService::class);
        $rows = $svc->barisSantri();

        $this->assertCount(1, $rows);
        $this->assertCount(count(EmisExportService::HEADER), $rows[0]);

        $i = fn (string $h): int => array_search($h, EmisExportService::HEADER, true);
        $this->assertSame('3211000000000090', $rows[0][$i('NO_KK')]);
        $this->assertSame('AL-1/2012', $rows[0][$i('NO_AKTA_LAHIR')]);
        $this->assertSame('Fulan', $rows[0][$i('NAMA_AYAH')]);
        $this->assertSame('7A', $rows[0][$i('ROMBEL')]);
        $this->assertSame('MTs Uji', $rows[0][$i('LEMBAGA')]);
        $this->assertSame('MTs', $rows[0][$i('JENJANG')]);

        // Peringatan #327: KK/akta kosong ikut ditandai.
        Santri::create(['nis' => 'E2', 'nama' => 'TanpaKk', 'status' => 'aktif', 'jenis_kelamin' => 'L', 'tanggal_lahir' => '2012-06-06',
            'nik' => '3211000000000003', 'nisn' => '0051234569']);
        $peringatan = $svc->peringatan('santri');
        $this->assertCount(1, $peringatan);
        $this->assertStringContainsString('No. KK kosong', $peringatan[0]);
        $this->assertStringContainsString('No. akta lahir kosong', $peringatan[0]);
        tenancy()->end();
    }
}
