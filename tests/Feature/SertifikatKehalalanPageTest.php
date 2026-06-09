<?php

namespace Tests\Feature;

use App\Filament\Pages\LaporanKehalalan;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G1-03 — Sertifikat & ekspor pada halaman Laporan Kehalalan (F3) memakai
 * ReportExportService (F8). Menguji glue data laporan → sertifikat/CSV.
 */
class SertifikatKehalalanPageTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'jurnal', 'jurnal_baris', 'sumber_dana', 'settings',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function bootPage(string $prefix): LaporanKehalalan
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        (new LedgerSeeder)->run();
        $this->seedTenantRow($tenant);

        $page = new LaporanKehalalan;
        $page->dari = now()->startOfYear()->toDateString();
        $page->sampai = now()->toDateString();

        return $page;
    }

    public function test_unduh_sertifikat_html_saat_pdf_belum_ada(): void
    {
        $page = $this->bootPage('skh1');
        $resp = $page->unduhSertifikat();

        $this->assertInstanceOf(Response::class, $resp);
        ob_start();
        $resp->sendContent();
        $isi = ob_get_clean();
        $this->assertStringContainsString('SERTIFIKAT KEHALALAN', $isi);
        $this->assertStringContainsString('Pesantren skh1', $isi); // fallback brand = nama tenant
        tenancy()->end();
    }

    public function test_ekspor_csv_ringkasan(): void
    {
        $page = $this->bootPage('skh2');
        $resp = $page->eksporCsv();

        $this->assertInstanceOf(StreamedResponse::class, $resp);
        ob_start();
        $resp->sendContent();
        $isi = ob_get_clean();
        $this->assertStringContainsString('Klasifikasi', $isi);
        $this->assertStringContainsString('Pendapatan Halal', $isi);
        $this->assertStringContainsString('Saldo Dana Kebajikan', $isi);
        $this->assertStringContainsString('text/csv', $resp->headers->get('Content-Type'));
        tenancy()->end();
    }
}
