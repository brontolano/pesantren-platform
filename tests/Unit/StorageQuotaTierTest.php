<?php

namespace Tests\Unit;

use App\Services\StorageQuotaService;
use Tests\TestCase;

/**
 * Gerbang tier object storage (S3): tier 64/128/256 GB hanya tersedia bila
 * media.object_storage aktif. Tidak menyentuh DB — validasi terjadi sebelum query.
 */
class StorageQuotaTierTest extends TestCase
{
    public function test_local_tiers_always_available(): void
    {
        config(['media.object_storage' => false]);

        $this->assertTrue(StorageQuotaService::isTierAvailable(8));
        $this->assertTrue(StorageQuotaService::isTierAvailable(16));
        $this->assertTrue(StorageQuotaService::isTierAvailable(32));
    }

    public function test_soon_tiers_locked_without_object_storage(): void
    {
        config(['media.object_storage' => false]);

        $this->assertFalse(StorageQuotaService::isTierAvailable(64));
        $this->assertFalse(StorageQuotaService::isTierAvailable(128));
        $this->assertFalse(StorageQuotaService::isTierAvailable(256));

        $availability = StorageQuotaService::tierAvailability();
        $this->assertFalse($availability[128]);
        $this->assertTrue($availability[32]);
    }

    public function test_soon_tiers_unlocked_with_object_storage(): void
    {
        config(['media.object_storage' => true]);

        $this->assertTrue(StorageQuotaService::isTierAvailable(64));
        $this->assertTrue(StorageQuotaService::isTierAvailable(256));
    }

    public function test_set_quota_rejects_soon_tier_when_object_storage_off(): void
    {
        config(['media.object_storage' => false]);

        $this->expectException(\InvalidArgumentException::class);

        (new StorageQuotaService)->setQuota('tenant-x', 128);
    }
}
