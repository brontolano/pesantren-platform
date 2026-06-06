<?php

namespace Tests\Unit;

use App\Support\ApiKeyExtractor;
use Tests\TestCase;

/**
 * Ekstraksi + validasi bentuk API key dari header. Pure, tanpa DB.
 */
class ApiKeyExtractorTest extends TestCase
{
    public function test_ambil_dari_x_api_key(): void
    {
        $this->assertSame('psa_abc123', ApiKeyExtractor::fromHeaders('psa_abc123', null));
        $this->assertSame('psa_abc123', ApiKeyExtractor::fromHeaders('  psa_abc123 ', null));
    }

    public function test_ambil_dari_bearer(): void
    {
        $this->assertSame('psa_xyz', ApiKeyExtractor::fromHeaders(null, 'Bearer psa_xyz'));
        $this->assertSame('psa_xyz', ApiKeyExtractor::fromHeaders(null, 'bearer psa_xyz')); // case-insensitive
    }

    public function test_x_api_key_diprioritaskan(): void
    {
        $this->assertSame('psa_header', ApiKeyExtractor::fromHeaders('psa_header', 'Bearer psa_bearer'));
    }

    public function test_kosong_null(): void
    {
        $this->assertNull(ApiKeyExtractor::fromHeaders(null, null));
        $this->assertNull(ApiKeyExtractor::fromHeaders('', ''));
        $this->assertNull(ApiKeyExtractor::fromHeaders(null, 'Bearer '));
        $this->assertNull(ApiKeyExtractor::fromHeaders(null, 'Basic abc'));
    }

    public function test_looks_valid(): void
    {
        $this->assertTrue(ApiKeyExtractor::looksValid('psa_'.str_repeat('a', 20)));
        $this->assertFalse(ApiKeyExtractor::looksValid('psa_short'));   // terlalu pendek
        $this->assertFalse(ApiKeyExtractor::looksValid('xyz_'.str_repeat('a', 20))); // prefix salah
    }
}
