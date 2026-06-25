<?php

namespace Tests\Unit;

use Modules\PPDB\Support\PpdbDocumentTypes;
use Tests\TestCase;

/**
 * Registry tipe dokumen PPDB + checklist wajib (DOC-4). Pure, tanpa DB.
 */
class PpdbDocumentTypesTest extends TestCase
{
    public function test_required_dan_optional(): void
    {
        $required = PpdbDocumentTypes::required();

        $this->assertContains('akta_kelahiran', $required);
        $this->assertContains('kartu_keluarga', $required);
        $this->assertContains('foto', $required);
        $this->assertNotContains('ijazah', $required); // ijazah opsional

        $this->assertContains('ijazah', PpdbDocumentTypes::optional());
        $this->assertContains('rapor', PpdbDocumentTypes::optional());
    }

    public function test_is_known_is_required_label(): void
    {
        $this->assertTrue(PpdbDocumentTypes::isKnown('foto'));
        $this->assertFalse(PpdbDocumentTypes::isKnown('paspor'));

        $this->assertTrue(PpdbDocumentTypes::isRequired('foto'));
        $this->assertFalse(PpdbDocumentTypes::isRequired('ijazah'));

        $this->assertSame('Pas Foto', PpdbDocumentTypes::label('foto'));
        $this->assertSame('paspor', PpdbDocumentTypes::label('paspor')); // fallback ke key
    }

    public function test_missing_required(): void
    {
        // baru unggah foto → kurang akta_kelahiran & kartu_keluarga
        $missing = PpdbDocumentTypes::missingRequired(['foto']);
        $this->assertContains('akta_kelahiran', $missing);
        $this->assertContains('kartu_keluarga', $missing);
        $this->assertNotContains('foto', $missing);

        // semua wajib terpenuhi → kosong
        $this->assertSame([], PpdbDocumentTypes::missingRequired([
            'akta_kelahiran', 'kartu_keluarga', 'foto', 'ijazah',
        ]));
    }

    public function test_keys_selaras_jumlah(): void
    {
        $this->assertCount(6, PpdbDocumentTypes::keys());
    }
}
