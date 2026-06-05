<?php

namespace App\Models;

use App\Services\StorageQuotaService;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * File media milik tenant (disimpan di DB tenant + storage terisolasi per tenant).
 * Soft-delete: file fisik baru dihapus saat force-delete (trash dikosongkan).
 */
class MediaFile extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'path',
        'original_name',
        'mime',
        'size_bytes',
        'folder',
        'uploaded_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    protected static function booted(): void
    {
        // Hapus file fisik HANYA saat force-delete (bukan soft-delete).
        static::forceDeleted(function (MediaFile $media): void {
            if ($media->path) {
                Storage::disk(StorageQuotaService::diskName())->delete($media->path);
            }
        });
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
