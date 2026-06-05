<?php

namespace App\Services;

use App\Models\MediaFile;
use Illuminate\Support\Facades\Storage;

/**
 * Mendaftarkan file yang tersimpan di disk tenant ke pustaka Media File
 * (tabel media_files) agar ter-index & tampil rapi di panel File Media.
 *
 * Dipakai oleh hook upload modul CMS (galeri/cover) & command media:reindex.
 * URL eksternal (referensi, mis. data migrasi galeri) DILEWATI — bukan file CMS.
 */
class MediaRegistrar
{
    /**
     * Daftarkan satu path disk ke media_files (idempotent).
     *
     * @param  string|null  $path    Path relatif di disk tenant (bukan URL).
     * @param  string|null  $folder  Label folder logis utk pengelompokan (mis. "Galeri/Wisuda VI").
     */
    public function register(?string $path, ?string $folder = null): ?MediaFile
    {
        if (! $path) {
            return null;
        }
        // Referensi URL eksternal bukan file CMS → lewati.
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return null;
        }

        $path = ltrim($path, '/');
        $disk = Storage::disk(StorageQuotaService::diskName());

        if (! $disk->exists($path)) {
            return null;
        }

        // Sudah terdaftar → sinkronkan folder bila berubah, jangan duplikat.
        $existing = MediaFile::withTrashed()->where('path', $path)->first();
        if ($existing) {
            if ($folder && $existing->folder !== $folder) {
                $existing->forceFill(['folder' => $folder])->save();
            }

            return $existing;
        }

        return MediaFile::create([
            'tenant_id' => tenant()?->getKey(),
            'path' => $path,
            'original_name' => basename($path),
            'mime' => $this->safeMime($disk, $path),
            'size_bytes' => (int) $disk->size($path),
            'folder' => $folder,
            'uploaded_by' => auth()->id(),
        ]);
    }

    private function safeMime($disk, string $path): ?string
    {
        try {
            return $disk->mimeType($path) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
