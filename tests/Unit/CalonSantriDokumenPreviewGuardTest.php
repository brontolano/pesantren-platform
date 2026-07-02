<?php

namespace Tests\Unit;

use Modules\PPDB\Filament\Resources\CalonSantriDokumenResource;
use Modules\PPDB\Models\CalonSantriDokumen;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guard preview in-panel: PDF → PDF viewer, gambar → ImageEntry, tipe lain
 * tidak dipreview (unduh). Murni logika ekstensi — tidak menyentuh DB.
 */
class CalonSantriDokumenPreviewGuardTest extends TestCase
{
    private function invokeGuard(string $method, CalonSantriDokumen $doc): bool
    {
        $m = new ReflectionMethod(CalonSantriDokumenResource::class, $method);
        $m->setAccessible(true);

        return (bool) $m->invoke(null, $doc);
    }

    public function test_pdf_dideteksi_sebagai_pdf(): void
    {
        $doc = new CalonSantriDokumen(['file_url' => 'ppdb/dok/ijazah-123.pdf']);

        $this->assertTrue($this->invokeGuard('dokumenPdf', $doc));
        $this->assertFalse($this->invokeGuard('dokumenGambar', $doc));
        $this->assertTrue($this->invokeGuard('dokumenDapatDipreview', $doc));
    }

    public function test_gambar_dideteksi_sebagai_gambar_bukan_pdf(): void
    {
        foreach (['scan.JPG', 'scan.jpeg', 'foto.png', 'x.webp'] as $path) {
            $doc = new CalonSantriDokumen(['file_url' => $path]);
            $this->assertTrue($this->invokeGuard('dokumenGambar', $doc), $path);
            $this->assertFalse($this->invokeGuard('dokumenPdf', $doc), $path);
            $this->assertTrue($this->invokeGuard('dokumenDapatDipreview', $doc), $path);
        }
    }

    public function test_tipe_lain_tidak_dipreview(): void
    {
        foreach (['dok.docx', 'arsip.zip', 'tanpaekstensi'] as $path) {
            $doc = new CalonSantriDokumen(['file_url' => $path]);
            $this->assertFalse($this->invokeGuard('dokumenDapatDipreview', $doc), $path);
        }
    }

    public function test_query_string_url_tidak_mengecoh_deteksi(): void
    {
        $doc = new CalonSantriDokumen(['file_url' => 'https://x.test/a/berkas.pdf?sig=abc']);

        $this->assertTrue($this->invokeGuard('dokumenPdf', $doc));
        $this->assertTrue($this->invokeGuard('dokumenDapatDipreview', $doc));
    }
}
