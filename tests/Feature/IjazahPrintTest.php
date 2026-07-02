<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use Modules\Akademik\Models\Lembaga;
use Modules\Akademik\Services\KelulusanService;
use Modules\Kesantrian\Models\Alumni;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Issue #328 — Template cetak Ijazah Muadalah/PKPPS per jenjang Ula/Wustha/Ulya.
 *
 * (a) Render HTML ijazah utk 1 santri dummy per jenjang memuat seluruh elemen
 *     wajib juknis: Garuda (aset), nomor ijazah, nama lembaga, NSPP, nama,
 *     TTL, NIS, NISN, jenjang, tanggal penerbitan, ttd pimpinan, slot
 *     stempel, slot pasfoto.
 * (b) Varian PKPPS memakai nomenklatur PKPPS.
 * (c) Placeholder baru syahadah ({{nisn}}/{{nspp}}/{{jenjang}}/{{nomor_ijazah}})
 *     terisi dari data santri/lembaga/alumni.
 */
class IjazahPrintTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'settings', 'santri', 'kelas',
            'lembaga', 'periode_akademik', 'alumni',
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

    /** @return array{alumni: Alumni, santri: Santri, lembaga: Lembaga} */
    private function seedAlumni(string $jenjang, string $suffix): array
    {
        $lembaga = Lembaga::create([
            'nama' => 'Muadalah '.ucfirst($jenjang).' '.$suffix,
            'jenis' => 'diniyah',
            'jenjang' => $jenjang,
            'nomor_statistik' => 'NSPP-51232-'.$suffix,
        ]);

        $santri = Santri::create([
            'nis' => 'NIS-'.$suffix,
            'nisn' => '00'.crc32($suffix),
            'nama' => 'Santri Uji '.$suffix,
            'tempat_lahir' => 'Sumedang',
            'tanggal_lahir' => '2010-05-05',
            'jenis_kelamin' => 'L',
            'status' => 'lulus',
        ]);

        $alumni = Alumni::create([
            'santri_id' => $santri->id,
            'tahun_lulus' => '2027',
            'tanggal_lulus' => '2027-06-20',
            'lembaga_id' => $lembaga->id,
            'nomor_ijazah' => 'IJZ-'.$suffix.'/2027',
        ]);

        return ['alumni' => $alumni->fresh(['santri', 'lembaga']), 'santri' => $santri, 'lembaga' => $lembaga];
    }

    private function renderIjazah(Alumni $alumni, string $jenis, string $jenjang): string
    {
        return view('exports.ijazah-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'alumni' => $alumni,
            'jenis' => $jenis,
            'jenjang' => $jenjang,
            'tempatTerbit' => 'Sumedang',
            'namaPimpinan' => 'KH. Fulan bin Fulan',
            'tanggalTerbit' => $alumni->tanggal_lulus,
        ])->render();
    }

    public function test_ijazah_muadalah_per_jenjang_memuat_elemen_wajib(): void
    {
        $this->bootTenant('ijz1');

        foreach (['ula', 'wustha', 'ulya'] as $jenjang) {
            $d = $this->seedAlumni($jenjang, strtoupper($jenjang));
            $html = $this->renderIjazah($d['alumni'], 'muadalah', $jenjang);

            $this->assertStringContainsString('SATUAN PENDIDIKAN MUADALAH', $html);
            $this->assertStringContainsString('JENJANG '.strtoupper($jenjang), $html);
            $this->assertStringContainsString('images/sertifikat/garuda.svg', $html); // lambang negara
            $this->assertStringContainsString('IJZ-'.strtoupper($jenjang).'/2027', $html); // nomor ijazah
            $this->assertStringContainsString($d['lembaga']->nama, $html);
            $this->assertStringContainsString('NSPP-51232-'.strtoupper($jenjang), $html); // NSPP
            $this->assertStringContainsString($d['santri']->nama, $html);
            $this->assertStringContainsString('Sumedang,', $html); // tempat lahir
            $this->assertStringContainsString('NIS-'.strtoupper($jenjang), $html);
            $this->assertStringContainsString((string) $d['santri']->nisn, $html); // NISN
            $this->assertStringContainsString('LULUS', $html);
            $this->assertStringContainsString('pada tanggal 20', $html); // tanggal penerbitan
            $this->assertStringContainsString('2027', $html);
            $this->assertStringContainsString('KH. Fulan bin Fulan', $html); // ttd pimpinan
            $this->assertStringContainsString('Stempel', $html); // slot stempel
            $this->assertStringContainsString('Pasfoto', $html); // slot pasfoto 3x4
        }

        tenancy()->end();
    }

    public function test_ijazah_pkpps_memakai_nomenklatur_pkpps(): void
    {
        $this->bootTenant('ijz2');
        $d = $this->seedAlumni('wustha', 'PK');

        $html = $this->renderIjazah($d['alumni'], 'pkpps', 'wustha');

        $this->assertStringContainsString('PENDIDIKAN KESETARAAN PADA PONDOK PESANTREN SALAFIYAH', $html);
        $this->assertStringNotContainsString('SATUAN PENDIDIKAN MUADALAH', $html);
        $this->assertStringContainsString('JENJANG WUSTHA', $html);
        $this->assertStringContainsString('NSPP-51232-PK', $html);

        tenancy()->end();
    }

    public function test_placeholder_syahadah_nisn_nspp_jenjang_terisi(): void
    {
        $this->bootTenant('ijz3');
        $d = $this->seedAlumni('ulya', 'SY');

        // Template kustom yang memakai placeholder baru #328.
        Setting::create([
            'key' => KelulusanService::SETTING_TEMPLATE_PREFIX.'default',
            'value' => "SYAHADAH {{nomor}}\nNISN: {{nisn}}\nNSPP: {{nspp}}\nJenjang: {{jenjang}}\nIjazah: {{nomor_ijazah}}",
        ]);

        $isi = app(KelulusanService::class)->renderSyahadah($d['alumni']);

        $this->assertStringContainsString('NISN: '.$d['santri']->nisn, $isi);
        $this->assertStringContainsString('NSPP: NSPP-51232-SY', $isi);
        $this->assertStringContainsString('Jenjang: ulya', $isi);
        $this->assertStringContainsString('Ijazah: IJZ-SY/2027', $isi);

        tenancy()->end();
    }
}
