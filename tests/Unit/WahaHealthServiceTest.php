<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Modules\Notifikasi\Services\WahaHealthService;
use Tests\TestCase;

/**
 * WahaHealthService memparsing status sesi WAHA dengan benar & fail-soft:
 * WORKING → sehat; SCAN_QR_CODE/STOPPED → tidak sehat; timeout/error → tidak
 * sehat TANPA throw. Tak menyentuh tenant/DB (murni HTTP fake + config).
 */
class WahaHealthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'notifikasi.waha_base_url' => 'http://waha.test',
            'notifikasi.waha_session' => 'default',
            'notifikasi.waha_api_key' => null,
        ]);
    }

    public function test_status_working_dianggap_sehat(): void
    {
        Http::fake([
            'waha.test/api/sessions*' => Http::response([
                ['name' => 'default', 'status' => 'WORKING'],
            ], 200),
        ]);

        $r = app(WahaHealthService::class)->check();

        $this->assertTrue($r['configured']);
        $this->assertTrue($r['healthy']);
        $this->assertSame('WORKING', $r['status']);
    }

    public function test_status_scan_qr_tidak_sehat(): void
    {
        Http::fake([
            'waha.test/api/sessions*' => Http::response([
                ['name' => 'default', 'status' => 'SCAN_QR_CODE'],
            ], 200),
        ]);

        $r = app(WahaHealthService::class)->check();

        $this->assertTrue($r['configured']);
        $this->assertFalse($r['healthy']);
        $this->assertSame('SCAN_QR_CODE', $r['status']);
    }

    public function test_status_stopped_tidak_sehat(): void
    {
        Http::fake([
            'waha.test/api/sessions*' => Http::response([
                ['name' => 'default', 'status' => 'STOPPED'],
            ], 200),
        ]);

        $r = app(WahaHealthService::class)->check();

        $this->assertFalse($r['healthy']);
        $this->assertSame('STOPPED', $r['status']);
    }

    public function test_sesi_tak_ditemukan_tidak_sehat(): void
    {
        Http::fake([
            'waha.test/api/sessions*' => Http::response([
                ['name' => 'lain', 'status' => 'WORKING'],
            ], 200),
        ]);

        $r = app(WahaHealthService::class)->check();

        $this->assertFalse($r['healthy']);
        $this->assertNull($r['status']);
    }

    public function test_timeout_tidak_sehat_tanpa_throw(): void
    {
        Http::fake([
            'waha.test/api/sessions*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'),
        ]);

        $r = app(WahaHealthService::class)->check(1);

        $this->assertTrue($r['configured']);
        $this->assertFalse($r['healthy']);
        $this->assertNull($r['status']);
        $this->assertNotNull($r['error']);
    }

    public function test_http_error_tidak_sehat(): void
    {
        Http::fake([
            'waha.test/api/sessions*' => Http::response('err', 500),
        ]);

        $r = app(WahaHealthService::class)->check();

        $this->assertFalse($r['healthy']);
    }

    public function test_base_url_kosong_tak_terkonfigurasi(): void
    {
        config(['notifikasi.waha_base_url' => null]);

        $r = app(WahaHealthService::class)->check();

        $this->assertFalse($r['configured']);
        $this->assertFalse($r['healthy']);
    }
}
