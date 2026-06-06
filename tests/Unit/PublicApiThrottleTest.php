<?php

namespace Tests\Unit;

use App\Support\PublicApiThrottle;
use Tests\TestCase;

/**
 * Aturan kunci & batas rate-limit API publik. Pure (config-driven), tanpa DB.
 */
class PublicApiThrottleTest extends TestCase
{
    public function test_kunci_per_api_key_paling_spesifik(): void
    {
        $this->assertSame(
            'pubapi:key:psa_ab12cd34',
            PublicApiThrottle::resolveKey('tenant-x', 'psa_ab12cd34', '1.2.3.4'),
        );
    }

    public function test_kunci_per_tenant_ip_saat_tanpa_apikey(): void
    {
        $this->assertSame(
            'pubapi:tenant:tenant-x:ip:1.2.3.4',
            PublicApiThrottle::resolveKey('tenant-x', null, '1.2.3.4'),
        );
    }

    public function test_kunci_per_ip_saat_tanpa_konteks(): void
    {
        $this->assertSame(
            'pubapi:ip:9.9.9.9',
            PublicApiThrottle::resolveKey(null, '  ', '9.9.9.9'),
        );
    }

    public function test_per_minute_dari_config(): void
    {
        config(['services.public_api.rate_per_minute' => 120]);
        $this->assertSame(120, PublicApiThrottle::perMinute());

        config(['services.public_api.rate_per_minute' => 0]);
        $this->assertSame(1, PublicApiThrottle::perMinute()); // minimal 1
    }
}
