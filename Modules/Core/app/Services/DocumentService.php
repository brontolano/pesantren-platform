<?php

namespace Modules\Core\Services;

use Modules\Core\Models\Document;
use RuntimeException;

/**
 * F5 — layanan repositori dokumen. Satu-satunya tempat logika simpan/versioning;
 * modul consumer memanggil via trait HasDocuments.
 */
class DocumentService
{
    /**
     * Simpan metadata dokumen. Versi otomatis: max(versi) per
     * (documentable, kategori) + 1.
     *
     * @param  array{path:string, judul:string, documentable_type?:?string, documentable_id?:?int, kategori?:?string, nama_asli?:?string, mime?:?string, ukuran?:?int, is_dokumen_halal?:bool, uploaded_by?:?int}  $data
     */
    public function simpan(array $data): Document
    {
        $this->ensureTenantContext();

        if (empty($data['path'])) {
            throw new RuntimeException('Path dokumen wajib diisi.');
        }

        $versi = Document::query()
            ->where('documentable_type', $data['documentable_type'] ?? null)
            ->where('documentable_id', $data['documentable_id'] ?? null)
            ->where('kategori', $data['kategori'] ?? null)
            ->max('versi');

        return Document::create([
            'documentable_type' => $data['documentable_type'] ?? null,
            'documentable_id' => $data['documentable_id'] ?? null,
            'kategori' => $data['kategori'] ?? null,
            'judul' => $data['judul'] ?? ($data['nama_asli'] ?? 'Dokumen'),
            'path' => $data['path'],
            'nama_asli' => $data['nama_asli'] ?? null,
            'mime' => $data['mime'] ?? null,
            'ukuran' => $data['ukuran'] ?? null,
            'versi' => ((int) $versi) + 1,
            'is_dokumen_halal' => (bool) ($data['is_dokumen_halal'] ?? false),
            'uploaded_by' => $data['uploaded_by'] ?? auth()->id(),
        ]);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk dokumen.');
        }
    }
}
