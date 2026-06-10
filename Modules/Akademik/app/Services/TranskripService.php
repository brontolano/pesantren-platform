<?php

namespace Modules\Akademik\Services;

use InvalidArgumentException;
use Modules\Core\Models\Document;
use Modules\Kesantrian\Models\Santri;
use RuntimeException;

/**
 * G3-22 — Penerbitan transkrip/ijazah sebagai Dokumen (F5 Document Repository,
 * versioning otomatis per kategori). Non-uang.
 */
class TranskripService
{
    public const JENIS = ['transkrip', 'ijazah'];

    /**
     * Terbitkan transkrip/ijazah → lampirkan Document ke santri (F5).
     *
     * @param  array{jenis?:string, periode?:string, judul?:string, path?:string, nama_asli?:string, uploaded_by?:int}  $data
     */
    public function terbitkan(Santri|int $santri, array $data = []): Document
    {
        $this->ensureTenantContext();
        $santri = $santri instanceof Santri ? $santri : Santri::query()->findOrFail($santri);

        $jenis = $data['jenis'] ?? 'transkrip';
        if (! in_array($jenis, self::JENIS, true)) {
            throw new InvalidArgumentException('Jenis dokumen harus transkrip|ijazah.');
        }
        $periode = $data['periode'] ?? 'final';

        return $santri->lampirkanDokumen([
            'kategori' => $jenis,
            'judul' => $data['judul'] ?? (ucfirst($jenis).' — '.$santri->nama),
            'path' => $data['path'] ?? ('transkrip/'.$jenis.'-santri-'.$santri->id.'-'.$periode.'.html'),
            'nama_asli' => $data['nama_asli'] ?? ($jenis.'-'.$santri->id.'.html'),
            'mime' => 'text/html',
            'uploaded_by' => $data['uploaded_by'] ?? null,
        ]);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
