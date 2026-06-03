<?php

namespace Modules\PPDB\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\CalonSantriDokumen;
use RuntimeException;

class PpdbDocumentUploadService
{
    private const ALLOWED_DOCUMENT_TYPES = [
        'ijazah',
        'akta_kelahiran',
        'foto',
        'kartu_keluarga',
        'rapor',
        'surat_keterangan',
    ];

    private const ALLOWED_EXTENSIONS = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'webp',
    ];

    public function upload(CalonSantri|int $calonSantri, string $jenisDokumen, UploadedFile $file, ?string $disk = null): CalonSantriDokumen
    {
        $this->ensureTenantContext();

        $calon = $calonSantri instanceof CalonSantri
            ? $calonSantri
            : CalonSantri::query()->findOrFail($calonSantri);

        $normalizedType = $this->normalizeDocumentType($jenisDokumen);
        $this->validateFile($file);

        $targetDisk = $disk ?: config('filesystems.default', 'local');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $directory = sprintf('ppdb-documents/%d/%s', $calon->id, $normalizedType);

        $path = $file->storeAs($directory, $filename, $targetDisk);

        return CalonSantriDokumen::create([
            'calon_santri_id' => $calon->id,
            'jenis_dokumen' => $normalizedType,
            'file_url' => $path,
            'metadata' => [
                'disk' => $targetDisk,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'extension' => $extension,
            ],
        ]);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk upload dokumen PPDB.');
        }
    }

    private function normalizeDocumentType(string $jenisDokumen): string
    {
        $normalized = Str::of($jenisDokumen)
            ->lower()
            ->replace([' ', '-'], '_')
            ->value();

        if (! in_array($normalized, self::ALLOWED_DOCUMENT_TYPES, true)) {
            throw new InvalidArgumentException('Jenis dokumen PPDB tidak didukung.');
        }

        return $normalized;
    }

    private function validateFile(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('Format file dokumen PPDB tidak didukung.');
        }

        $maxBytes = 5 * 1024 * 1024;

        if (($file->getSize() ?? 0) > $maxBytes) {
            throw new InvalidArgumentException('Ukuran file dokumen PPDB melebihi batas 5MB.');
        }
    }
}
