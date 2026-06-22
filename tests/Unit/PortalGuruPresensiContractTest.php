<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\PortalGuru\PresensiJurnalData;
use App\Data\PortalGuru\PresensiPageData;
use App\Data\PortalGuru\PresensiSantriItemData;
use App\Data\PortalGuru\PresensiSesiData;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Mengunci KONTRAK payload PortalGuru/Presensi → frontend (resources/js/types/guru.d.ts).
 * Wire-format SENGAJA snake_case (jam_mulai/jam_selesai/jurnal_url) = identik kontrak lama.
 * Murni unit, tanpa DB.
 */
final class PortalGuruPresensiContractTest extends TestCase
{
    public function test_sesi_jurnal_pakai_snake_case_kontrak_lama(): void
    {
        $sesi = new PresensiSesiData(7, 'Nahwu', 'X-A', '07:00', '08:30');
        self::assertSame(['id', 'pelajaran', 'kelas', 'jam_mulai', 'jam_selesai'], array_keys($sesi->toArray()));
        self::assertSame('07:00', $sesi->toArray()['jam_mulai']);

        $jurnal = new PresensiJurnalData('Bab Idhafah', 'Ceramah', 'mengajar', null, 18);
        self::assertSame(['materi', 'metode', 'status', 'pengganti_id', 'jumlah_hadir'], array_keys($jurnal->toArray()));
        self::assertSame(18, $jurnal->toArray()['jumlah_hadir']);
    }

    public function test_baris_santri_default_hadir(): void
    {
        $row = new PresensiSantriItemData(101, '2024001', 'Ahmad', 'hadir');
        self::assertSame(['id', 'nis', 'nama', 'status'], array_keys($row->toArray()));
        self::assertSame('hadir', $row->toArray()['status']);
    }

    public function test_agregat_presensi_page_props_lengkap(): void
    {
        $payload = (new PresensiPageData(
            sesi: new PresensiSesiData(7, 'Nahwu', 'X-A', '07:00', '08:30'),
            tanggal: '2026-06-22',
            santri: new Collection([
                new PresensiSantriItemData(101, '2024001', 'Ahmad', 'hadir'),
                new PresensiSantriItemData(102, '2024002', 'Bilal', 'izin'),
            ]),
            statusOptions: ['hadir', 'izin', 'sakit', 'alpa'],
            jurnal: null,
            jurnalUrl: '/guru/jurnal/7',
        ))->toArray();

        self::assertSame(['sesi', 'tanggal', 'santri', 'statusOptions', 'jurnal', 'jurnal_url'], array_keys($payload));
        self::assertCount(2, $payload['santri']);
        self::assertSame('izin', $payload['santri'][1]['status']);
        self::assertSame(['hadir', 'izin', 'sakit', 'alpa'], $payload['statusOptions']);
        self::assertNull($payload['jurnal']);
        self::assertSame('/guru/jurnal/7', $payload['jurnal_url']);
    }
}
