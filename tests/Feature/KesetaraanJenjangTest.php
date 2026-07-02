<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Modules\Akademik\Models\Lembaga;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\KesetaraanTemplateService;
use Modules\Keuangan\Services\EmisExportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * #338 — Pemetaan kelas diniyah internal → jenjang kesetaraan (Ula/Wustha/Ulya).
 */
class KesetaraanJenjangTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'kelas', 'santri', 'lembaga', 'tagihan']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        // Pola EmisExportKategoriTest: tenant runtime = DB fisik baru + tenants:migrate
        // → sekaligus menguji portabilitas migrasi jenjang_kesetaraan.
        $this->tenant = $this->createRuntimeTenant('kst', 'Pesantren Kesetaraan');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function buatKelas(string $nama, string $tingkat, array $extra = []): Kelas
    {
        return Kelas::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'nama_kelas' => $nama,
            'tingkat' => $tingkat,
            'capacity' => 30,
        ], $extra));
    }

    public function test_kelas_menyimpan_jenjang_dan_tingkat_kesetaraan(): void
    {
        $kelas = $this->buatKelas('Marhalah 3', '3', [
            'jenjang_kesetaraan' => 'wustha',
            'tingkat_kesetaraan' => 2,
        ]);

        $kelas->refresh();
        $this->assertSame('wustha', $kelas->jenjang_kesetaraan);
        $this->assertSame(2, $kelas->tingkat_kesetaraan);
        $this->assertSame('Wustha 2', $kelas->label_kesetaraan);
    }

    public function test_default_jenjang_kesetaraan_diturunkan_dari_lembaga(): void
    {
        $lembaga = Lembaga::create([
            'tenant_id' => $this->tenant->id,
            'nama' => 'PKPPS Wustha',
            'jenis' => 'diniyah',
            'jenjang' => 'Wustha', // huruf kapital — harus dinormalisasi
        ]);

        $kelas = $this->buatKelas('Kelas 1 Wustha', '1', ['lembaga_id' => $lembaga->id]);

        $this->assertSame('wustha', $kelas->refresh()->jenjang_kesetaraan);
    }

    public function test_default_tidak_dipaksakan_bila_jenjang_lembaga_bukan_kesetaraan(): void
    {
        $lembaga = Lembaga::create([
            'tenant_id' => $this->tenant->id,
            'nama' => 'MTs Formal',
            'jenis' => 'formal',
            'jenjang' => 'MTs',
        ]);

        $kelas = $this->buatKelas('VII-A', '7', ['lembaga_id' => $lembaga->id]);

        $this->assertNull($kelas->refresh()->jenjang_kesetaraan);
    }

    public function test_template_memetakan_kelas_berurutan_ula_wustha_ulya(): void
    {
        // 12 kelas tanpa lembaga → tangga penuh Ula 1-6, Wustha 1-3, Ulya 1-3
        foreach (range(1, 12) as $i) {
            $this->buatKelas("Marhalah {$i}", (string) $i);
        }

        $jumlah = app(KesetaraanTemplateService::class)->terapkan(null);

        $this->assertSame(12, $jumlah);
        $urut = Kelas::query()->get()->sortBy(fn (Kelas $k) => (int) $k->tingkat)->values();

        $this->assertSame(['ula', 1], [$urut[0]->jenjang_kesetaraan, $urut[0]->tingkat_kesetaraan]);
        $this->assertSame(['ula', 6], [$urut[5]->jenjang_kesetaraan, $urut[5]->tingkat_kesetaraan]);
        $this->assertSame(['wustha', 1], [$urut[6]->jenjang_kesetaraan, $urut[6]->tingkat_kesetaraan]);
        $this->assertSame(['wustha', 3], [$urut[8]->jenjang_kesetaraan, $urut[8]->tingkat_kesetaraan]);
        $this->assertSame(['ulya', 1], [$urut[9]->jenjang_kesetaraan, $urut[9]->tingkat_kesetaraan]);
        $this->assertSame(['ulya', 3], [$urut[11]->jenjang_kesetaraan, $urut[11]->tingkat_kesetaraan]);
    }

    public function test_template_per_lembaga_mengikuti_jenjang_lembaga(): void
    {
        $lembaga = Lembaga::create([
            'tenant_id' => $this->tenant->id,
            'nama' => 'PKPPS Ulya',
            'jenis' => 'diniyah',
            'jenjang' => 'ulya',
        ]);

        // Kelas otomatis dapat jenjang 'ulya' dari lembaga (hook creating),
        // tapi tingkat masih kosong → template (timpa) harus mengisinya.
        $k1 = $this->buatKelas('Ulya A', '1', ['lembaga_id' => $lembaga->id]);
        $k2 = $this->buatKelas('Ulya B', '2', ['lembaga_id' => $lembaga->id]);

        $jumlah = app(KesetaraanTemplateService::class)->terapkan($lembaga->id, timpa: true);

        $this->assertSame(2, $jumlah);
        $this->assertSame(['ulya', 1], [$k1->refresh()->jenjang_kesetaraan, $k1->tingkat_kesetaraan]);
        $this->assertSame(['ulya', 2], [$k2->refresh()->jenjang_kesetaraan, $k2->tingkat_kesetaraan]);
    }

    public function test_template_tanpa_timpa_tidak_mengubah_pemetaan_manual(): void
    {
        $manual = $this->buatKelas('Manual', '1', [
            'jenjang_kesetaraan' => 'ulya',
            'tingkat_kesetaraan' => 3,
        ]);
        $this->buatKelas('Kosong', '2');

        app(KesetaraanTemplateService::class)->terapkan(null);

        $this->assertSame(['ulya', 3], [$manual->refresh()->jenjang_kesetaraan, $manual->tingkat_kesetaraan]);
    }

    public function test_accessor_santri_mengikuti_kelas_aktif(): void
    {
        $kelas = $this->buatKelas('Marhalah 2', '2', [
            'jenjang_kesetaraan' => 'ula',
            'tingkat_kesetaraan' => 2,
        ]);

        $santri = Santri::create([
            'tenant_id' => $this->tenant->id,
            'nis' => 'S338',
            'nama' => 'Ahmad',
            'tanggal_lahir' => '2015-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'kelas_id' => $kelas->id,
        ]);

        $this->assertSame('ula', $santri->jenjang_kesetaraan);
        $this->assertSame(2, $santri->tingkat_kesetaraan);
        $this->assertSame('Ula 2', $santri->label_kesetaraan);
    }

    public function test_emis_export_menyertakan_jenjang_kesetaraan_santri(): void
    {
        $kelas = $this->buatKelas('Marhalah 1', '1', [
            'jenjang_kesetaraan' => 'wustha',
            'tingkat_kesetaraan' => 1,
        ]);

        Santri::create([
            'tenant_id' => $this->tenant->id,
            'nis' => 'S339',
            'nama' => 'Budi',
            'tanggal_lahir' => '2013-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'kelas_id' => $kelas->id,
        ]);

        $iJenjang = array_search('JENJANG_KESETARAAN', EmisExportService::HEADER, true);
        $iTingkat = array_search('TINGKAT_KESETARAAN', EmisExportService::HEADER, true);
        $this->assertNotFalse($iJenjang);
        $this->assertNotFalse($iTingkat);

        $rows = app(EmisExportService::class)->barisSantri();
        $this->assertCount(count(EmisExportService::HEADER), $rows[0]);
        $this->assertSame('WUSTHA', $rows[0][$iJenjang]);
        $this->assertSame('1', $rows[0][$iTingkat]);
    }
}
