<?php

namespace Tests\Unit;

use Modules\PPDB\Models\CalonSantriDokumen;
use Tests\TestCase;

/**
 * Status verifikasi dokumen peserta (F2/DOC-3). Murni logika model —
 * tidak menyentuh DB (hanya akses atribut in-memory).
 */
class CalonSantriDokumenStatusTest extends TestCase
{
    public function test_dokumen_baru_dianggap_pending(): void
    {
        $doc = new CalonSantriDokumen;

        $this->assertSame(CalonSantriDokumen::STATUS_PENDING, $doc->effectiveStatus());
        $this->assertTrue($doc->isPending());
        $this->assertFalse($doc->isVerified());
        $this->assertFalse($doc->isRejected());
        $this->assertSame('Menunggu Verifikasi', $doc->statusLabel());
    }

    public function test_status_verified(): void
    {
        $doc = new CalonSantriDokumen(['status' => CalonSantriDokumen::STATUS_VERIFIED]);

        $this->assertTrue($doc->isVerified());
        $this->assertFalse($doc->isPending());
        $this->assertSame('Terverifikasi', $doc->statusLabel());
    }

    public function test_status_rejected(): void
    {
        $doc = new CalonSantriDokumen(['status' => CalonSantriDokumen::STATUS_REJECTED]);

        $this->assertTrue($doc->isRejected());
        $this->assertFalse($doc->isPending());
        $this->assertSame('Ditolak', $doc->statusLabel());
    }

    public function test_label_map_lengkap(): void
    {
        $this->assertArrayHasKey(CalonSantriDokumen::STATUS_PENDING, CalonSantriDokumen::STATUS_LABELS);
        $this->assertArrayHasKey(CalonSantriDokumen::STATUS_VERIFIED, CalonSantriDokumen::STATUS_LABELS);
        $this->assertArrayHasKey(CalonSantriDokumen::STATUS_REJECTED, CalonSantriDokumen::STATUS_LABELS);
    }
}
