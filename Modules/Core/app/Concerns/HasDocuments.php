<?php

namespace Modules\Core\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Core\Models\Document;
use Modules\Core\Services\DocumentService;

/**
 * F5 — pasang `use HasDocuments` pada model yang punya lampiran (Mutu, Santri,
 * Belanja, Aset). Modul consumer tak bikin tabel/kolom file sendiri.
 */
trait HasDocuments
{
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** Lampirkan dokumen (versioning otomatis per kategori). */
    public function lampirkanDokumen(array $data): Document
    {
        return app(DocumentService::class)->simpan(array_merge($data, [
            'documentable_type' => static::class,
            'documentable_id' => $this->getKey(),
        ]));
    }

    /** Dokumen versi terbaru untuk satu kategori. */
    public function dokumenTerbaru(string $kategori): ?Document
    {
        return $this->documents()->where('kategori', $kategori)->orderByDesc('versi')->first();
    }
}
