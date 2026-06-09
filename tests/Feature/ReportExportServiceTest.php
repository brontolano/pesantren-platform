<?php

namespace Tests\Feature;

use App\Models\Setting;
use InvalidArgumentException;
use Modules\Core\Exceptions\ReportPdfUnavailableException;
use Modules\Core\Services\ReportExportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F8 — Report & Export Service. Menguji kop brand, render template (sertifikat
 * kehalalan + generik), CSV native, fallback PDF saat library belum terpasang,
 * registry template, dan isolasi tenant.
 */
class ReportExportServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'settings',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    private function setBrandNama(string $nama): void
    {
        Setting::create(['key' => 'branding_name', 'value' => ['text' => $nama]]);
    }

    public function test_brand_dari_setting_dan_fallback(): void
    {
        $this->boot('rpt1');
        $this->setBrandNama('Pondok Uji');

        $brand = app(ReportExportService::class)->brand();
        $this->assertSame('Pondok Uji', $brand['nama']);
        $this->assertNotEmpty($brand['warna']); // default fallback
        tenancy()->end();
    }

    public function test_html_sertifikat_kehalalan(): void
    {
        $this->boot('rpt2');
        $this->setBrandNama('Pondok Uji');

        $html = app(ReportExportService::class)->html('sertifikat-kehalalan', [
            'periode' => '2026', 'persen_halal' => 95, 'status' => 'Sesuai Syariah',
        ]);

        $this->assertStringContainsString('SERTIFIKAT KEHALALAN', $html);
        $this->assertStringContainsString('Pondok Uji', $html);
        $this->assertStringContainsString('95', $html);
        tenancy()->end();
    }

    public function test_html_generik_tabel(): void
    {
        $this->boot('rpt3');
        $this->setBrandNama('Pondok Uji');

        $html = app(ReportExportService::class)->html('laporan-mutu', [
            'judul' => 'Laporan Mutu Internal',
            'kolom' => ['Indikator', 'Skor'],
            'baris' => [['Kebersihan', '88'], ['Disiplin', '92']],
        ]);

        $this->assertStringContainsString('Laporan Mutu Internal', $html);
        $this->assertStringContainsString('Kebersihan', $html);
        $this->assertStringContainsString('92', $html);
        tenancy()->end();
    }

    public function test_csv_native(): void
    {
        $this->boot('rpt4');
        $resp = app(ReportExportService::class)->csv(['Nama', 'Nilai'], [['Andi', 100], ['Budi', 200]], 'nilai.csv');

        ob_start();
        $resp->sendContent();
        $isi = ob_get_clean();

        $this->assertStringContainsString('Nama', $isi);
        $this->assertStringContainsString('Andi', $isi);
        $this->assertStringContainsString('200', $isi);
        $this->assertStringContainsString('text/csv', $resp->headers->get('Content-Type'));
        tenancy()->end();
    }

    public function test_pdf_tidak_tersedia_lempar_exception(): void
    {
        $this->boot('rpt5');
        $svc = app(ReportExportService::class);
        // Library dompdf belum terpasang di proyek → harus melempar, bukan fatal.
        $this->assertFalse($svc->pdfTersedia());
        $this->expectException(ReportPdfUnavailableException::class);
        $svc->pdf('sertifikat-kehalalan', ['periode' => '2026']);
        tenancy()->end();
    }

    public function test_template_tak_terdaftar(): void
    {
        $this->boot('rpt6');
        $this->expectException(InvalidArgumentException::class);
        app(ReportExportService::class)->view('template-ngawur');
        tenancy()->end();
    }

    public function test_isolasi_tenant(): void
    {
        $this->boot('rptA');
        $this->setBrandNama('Pondok A');
        $this->assertSame('Pondok A', app(ReportExportService::class)->brand()['nama']);
        tenancy()->end();

        $this->boot('rptB');
        $this->assertNotSame('Pondok A', app(ReportExportService::class)->brand()['nama']);
        tenancy()->end();
    }
}
