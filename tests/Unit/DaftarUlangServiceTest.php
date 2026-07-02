<?php

namespace Tests\Unit;

use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Services\DaftarUlangService;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * Guard daftar ulang (F3/REREG) + pemetaan jenis kelamin. Tanpa DB — guard
 * dilempar sebelum transaksi; pemetaan diuji via refleksi.
 */
class DaftarUlangServiceTest extends TestCase
{
    public function test_tolak_peserta_non_lulus(): void
    {
        $calon = new CalonSantri;
        $calon->status_pendaftaran = 'pending';

        $this->expectException(RuntimeException::class);

        (new DaftarUlangService)->process($calon);
    }

    public function test_tolak_lulus_tanpa_tanggal_lahir(): void
    {
        $calon = new CalonSantri;
        $calon->status_pendaftaran = 'lulus'; // tanggal_lahir null → ditolak sebelum DB

        $this->expectException(RuntimeException::class);

        (new DaftarUlangService)->process($calon);
    }

    public function test_map_jenis_kelamin(): void
    {
        $svc = new DaftarUlangService;
        $m = new ReflectionMethod($svc, 'mapJenisKelamin');
        $m->setAccessible(true);

        $this->assertSame('P', $m->invoke($svc, 'Perempuan'));
        $this->assertSame('P', $m->invoke($svc, 'p'));
        $this->assertSame('L', $m->invoke($svc, 'laki-laki'));
        $this->assertSame('L', $m->invoke($svc, 'L'));
        $this->assertSame('L', $m->invoke($svc, '')); // default L
    }

    public function test_konstanta_status_selesai(): void
    {
        $this->assertSame('daftar_ulang', DaftarUlangService::STATUS_SELESAI);
    }
}
