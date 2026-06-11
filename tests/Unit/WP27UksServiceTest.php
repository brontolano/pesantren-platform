<?php

namespace Tests\Unit;

use Modules\Kesehatan\Models\KunjunganUks;
use Modules\Kesehatan\Services\KesehatanService;
use Modules\Kesantrian\Models\Santri;
use PHPUnit\Framework\TestCase;

/**
 * WP-27 — Unit tests untuk UksService (logic testing tanpa DB full migration).
 */
class WP27UksServiceTest extends TestCase
{
    private KesehatanService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KesehatanService();
    }

    public function test_service_instantiated(): void
    {
        $this->assertInstanceOf(KesehatanService::class, $this->service);
    }

    public function test_kunjungan_uks_has_expected_table_name(): void
    {
        $model = new KunjunganUks();
        $this->assertEquals('kunjungan_uks', $model->getTable());
    }

    public function test_kunjungan_uks_status_constants(): void
    {
        $this->assertContains('pulang', KunjunganUks::STATUS);
        $this->assertContains('istirahat', KunjunganUks::STATUS);
        $this->assertContains('rujuk', KunjunganUks::STATUS);
        $this->assertCount(3, KunjunganUks::STATUS);
    }

    public function test_service_method_catat_kunjungan_exists(): void
    {
        $this->assertTrue(method_exists($this->service, 'catatKunjungan'));
    }

    public function test_service_method_tutup_kunjungan_exists(): void
    {
        $this->assertTrue(method_exists($this->service, 'tutupKunjungan'));
    }

    public function test_service_method_get_daftar_kunjungan_exists(): void
    {
        $this->assertTrue(method_exists($this->service, 'getDaftarKunjungan'));
    }

    public function test_service_method_get_kunjungan_hari_ini_exists(): void
    {
        $this->assertTrue(method_exists($this->service, 'getKunjunganHariIni'));
    }

    public function test_service_method_get_daftar_istirahat_exists(): void
    {
        $this->assertTrue(method_exists($this->service, 'getDaftarIstirahat'));
    }

    public function test_service_method_get_daftar_rujukan_terbuka_exists(): void
    {
        $this->assertTrue(method_exists($this->service, 'getDaftarRujukanTerbuka'));
    }
}
