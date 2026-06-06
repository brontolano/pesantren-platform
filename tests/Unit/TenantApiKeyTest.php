<?php

namespace Tests\Unit;

use App\Models\TenantApiKey;
use Tests\TestCase;

/**
 * API key per tenant — generate/hash/verify. Murni logika (tanpa DB).
 */
class TenantApiKeyTest extends TestCase
{
    public function test_hash_deterministik_dan_sha256(): void
    {
        $h1 = TenantApiKey::hashKey('rahasia');
        $h2 = TenantApiKey::hashKey('rahasia');

        $this->assertSame($h1, $h2);
        $this->assertSame(64, strlen($h1));                 // sha256 hex
        $this->assertNotSame($h1, TenantApiKey::hashKey('beda'));
    }

    public function test_generate_plain_key_berprefix_dan_acak(): void
    {
        $a = TenantApiKey::generatePlainKey();
        $b = TenantApiKey::generatePlainKey();

        $this->assertStringStartsWith('psa_', $a);
        $this->assertGreaterThan(40, strlen($a));
        $this->assertNotSame($a, $b); // acak
    }

    public function test_build_for_menghasilkan_hash_cocok(): void
    {
        $built = TenantApiKey::buildFor('tenant-demo', 'Website utama');

        $this->assertSame('tenant-demo', $built['attributes']['tenant_id']);
        $this->assertSame(TenantApiKey::hashKey($built['plain']), $built['attributes']['key_hash']);
        $this->assertStringStartsWith('psa_', $built['attributes']['prefix']);
        $this->assertArrayNotHasKey('plain', $built['attributes']); // plaintext tak ikut tersimpan
    }

    public function test_matches_timing_safe(): void
    {
        $key = new TenantApiKey(['key_hash' => TenantApiKey::hashKey('secret-123')]);

        $this->assertTrue($key->matches('secret-123'));
        $this->assertFalse($key->matches('secret-999'));
    }

    public function test_is_active_berdasar_revoked_at(): void
    {
        $aktif = new TenantApiKey(['revoked_at' => null]);
        $this->assertTrue($aktif->isActive());

        $dicabut = new TenantApiKey(['revoked_at' => now()]);
        $this->assertFalse($dicabut->isActive());
    }
}
