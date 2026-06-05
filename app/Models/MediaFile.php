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

        // Audit trail.
        static::created(fn (MediaFile $m) => self::writeAudit('upload', $m));
        static::deleted(fn (MediaFile $m) => self::writeAudit($m->isForceDeleting() ? 'kosongkan' : 'hapus', $m));
        static::restored(fn (MediaFile $m) => self::writeAudit('pulihkan', $m));
    }

    /** Catat aksi ke media_audit (tidak boleh mengganggu operasi utama). */
    protected static function writeAudit(string $action, self $media): void
    {
        try {
            MediaAudit::create([
                'action' => $action,
                'media_file_id' => $media->getKey(),
                'file_name' => $media->original_name,
                'size_bytes' => (int) ($media->size_bytes ?? 0),
                'user_id' => auth()->id(),
            ]);
        } catch (\Throwable) {
            // diam — audit gagal tidak boleh memblokir upload/hapus.
        }
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
