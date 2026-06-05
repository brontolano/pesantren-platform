<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaFileResource;
use App\Models\MediaFile;
use App\Services\MediaImageService;
use App\Services\StorageQuotaService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateMediaFile extends CreateRecord
{
    protected static string $resource = MediaFileResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $diskName = StorageQuotaService::diskName();
        $disk = Storage::disk($diskName);
        $path = is_array($data['path'] ?? null) ? ($data['path'][0] ?? null) : ($data['path'] ?? null);

        $mime = ($path && $disk->exists($path)) ? $disk->mimeType($path) : null;

        // Kompres gambar (in-place) SEBELUM hitung ukuran & kuota — hemat ruang.
        if ($path && $mime) {
            app(MediaImageService::class)->compress($diskName, $path, $mime);
        }

        $size = ($path && $disk->exists($path)) ? (int) $disk->size($path) : 0;

        $quota = app(StorageQuotaService::class);
        $tenantId = tenant()?->getKey() ?? '';

        if (! $quota->canUpload($tenantId, $size)) {
            if ($path) {
                $disk->delete($path);
            }
            Notification::make()
                ->title('Kuota penyimpanan penuh')
                ->body('Sisa kuota: '.StorageQuotaService::humanBytes($quota->remainingBytes($tenantId)).'. Hapus file lama atau upgrade kuota.')
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }

        $original = $data['original_name'] ?? null;
        if (is_array($original)) {
            $original = reset($original) ?: null;
        }

        return MediaFile::create([
            'path' => $path,
            'original_name' => $original ?: basename((string) $path),
            'mime' => $mime,
            'size_bytes' => $size,
            'folder' => $data['folder'] ?? null,
            'uploaded_by' => auth()->id(),
        ]);
    }
}
