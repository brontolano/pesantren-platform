<?php

namespace Tests\Unit;

use App\Services\StorageQuotaService;
use Tests\TestCase;

/**
 * Resolusi disk media: otomatis ke disk S3 saat object storage aktif,
 * disk lokal saat tidak. Config-driven, tanpa DB.
 */
class StorageQuotaDiskTest extends TestCase
{
    public function test_disk_lokal_saat_object_storage_mati(): void
    {
        config(['media.object_storage' => false, 'media.disk' => 'public']);

        $this->assertSame('public', StorageQuotaService::diskName());
    }

    public function test_disk_s3_otomatis_saat_object_storage_aktif(): void
    {
        config(['media.object_storage' => true, 'media.disk' => 'public']);

        $this->assertSame('s3', StorageQuotaService::diskName());
    }

    public function test_disk_object_storage_bisa_dikustom(): void
    {
        config([
            'media.object_storage' => true,
            'media.object_storage_disk' => 'minio',
        ]);

        $this->assertSame('minio', StorageQuotaService::diskName());
    }
}
