<?php

declare(strict_types=1);

namespace Modules\CMS\Filament\Resources\Gallery\Concerns;

/**
 * Menangani sinkronisasi foto dari field staging "new_photos" (FileUpload
 * multiple, satu dropzone) menjadi baris GalleryImage milik galeri.
 *
 * Alur: field "new_photos" menampung path file yang baru diunggah. Sebelum
 * model di-simpan, path-path itu di-capture & dibuang dari $data (bukan kolom
 * galleries). Setelah record tersimpan, tiap path dibuatkan baris GalleryImage
 * (saved hook GalleryImage otomatis mendaftarkannya ke media_files).
 */
trait SyncsGalleryPhotos
{
    /** @var array<int,string> */
    protected array $stagedPhotos = [];

    /**
     * Ambil path foto baru dari $data lalu buang dari array (agar tidak
     * dianggap kolom/relasi galleries saat model di-fill).
     *
     * @param  array<string,mixed>  $data
     */
    protected function captureStagedPhotos(array &$data): void
    {
        $this->stagedPhotos = array_values(array_filter(
            (array) ($data['new_photos'] ?? []),
            static fn ($p): bool => is_string($p) && $p !== '',
        ));

        unset($data['new_photos']);
    }

    /** Buat baris GalleryImage untuk tiap foto staging, menyambung urutan terakhir. */
    protected function persistStagedPhotos(): void
    {
        if ($this->stagedPhotos === [] || $this->record === null) {
            return;
        }

        $start = (int) $this->record->images()->max('sort_order');

        foreach ($this->stagedPhotos as $i => $path) {
            $this->record->images()->create([
                'image_url' => $path,
                'sort_order' => $start + $i + 1,
                'is_featured' => false,
            ]);
        }

        $this->stagedPhotos = [];
    }
}
