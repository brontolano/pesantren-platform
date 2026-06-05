<?php

namespace App\Services;

use App\Models\MediaFile;
use App\Models\TenantStorage;

/**
 * Sumber kebenaran kuota penyimpanan per tenant.
 *
 * - Kuota disimpan di tabel tenant_storage (CENTRAL).
 * - Pemakaian = SUM(media_files.size_bytes) di DB tenant aktif.
 * - Default 8 GB; tenant tanpa baris tenant_storage memakai default.
 */
class StorageQuotaService
{
    public const DEFAULT_GB = 8;

    /** Tier yang bisa dipilih sekarang (disk lokal). */
    public const TIERS_AVAILABLE = [8, 16, 32];

    /** Tier yang butuh object storage (Soon). */
    public const TIERS_SOON = [64, 128, 256];

    private const BYTES_PER_GB = 1073741824; // 1024^3

    /** Disk media aktif (public lokal, atau s3 bila object storage). */
    public static function diskName(): string
    {
        return config('media.disk', 'public');
    }

    /** Batas ukuran per file dalam KB (untuk Filament FileUpload). */
    public static function maxFileKb(): int
    {
        return ((int) config('media.max_file_mb', 100)) * 1024;
    }

    /** Apakah object storage sudah siap (membuka tier 64/128/256 GB). */
    public static function objectStorageReady(): bool
    {
        return (bool) config('media.object_storage', false);
    }

    /** Semua tier + status ketersediaan: [gb => bool tersedia]. */
    public static function tierAvailability(): array
    {
        $out = [];
        foreach (self::TIERS_AVAILABLE as $gb) {
            $out[$gb] = true;
        }
        foreach (self::TIERS_SOON as $gb) {
            $out[$gb] = self::objectStorageReady();
        }

        return $out;
    }

    public function quotaGb(string $tenantId): int
    {
        $row = TenantStorage::query()->where('tenant_id', $tenantId)->first();

        return $row?->quota_gb ?? self::DEFAULT_GB;
    }

    public function quotaBytes(string $tenantId): int
    {
        return $this->quotaGb($tenantId) * self::BYTES_PER_GB;
    }

    /**
     * Pemakaian byte tenant aktif (dipanggil dalam konteks tenant).
     * Termasuk file ter-trash — file fisik masih ada sampai force-delete.
     */
    public function usedBytes(): int
    {
        return (int) MediaFile::withTrashed()->sum('size_bytes');
    }

    public function remainingBytes(string $tenantId): int
    {
        return max(0, $this->quotaBytes($tenantId) - $this->usedBytes());
    }

    public function canUpload(string $tenantId, int $sizeBytes): bool
    {
        return ($this->usedBytes() + $sizeBytes) <= $this->quotaBytes($tenantId);
    }

    public function percentUsed(string $tenantId): float
    {
        $quota = $this->quotaBytes($tenantId);
        if ($quota <= 0) {
            return 100.0;
        }

        return min(100.0, round($this->usedBytes() / $quota * 100, 1));
    }

    /** Set/ubah kuota tenant (GB). */
    public function setQuota(string $tenantId, int $quotaGb): TenantStorage
    {
        return TenantStorage::updateOrCreate(
            ['tenant_id' => $tenantId],
            ['quota_gb' => max(1, $quotaGb)],
        );
    }

    public static function humanBytes(int $bytes): string
    {
        if ($bytes >= self::BYTES_PER_GB) {
            return round($bytes / self::BYTES_PER_GB, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
