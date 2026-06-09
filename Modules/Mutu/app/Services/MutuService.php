<?php

namespace Modules\Mutu\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Mutu\Models\DokumenMutu;
use Modules\Mutu\Models\DokumenMutuVersi;
use RuntimeException;

/**
 * Logika bisnis modul Mutu (#27): alur approval dokumen + versioning.
 *
 * State-machine status_approval:
 *   draft → diajukan → disetujui | ditolak.
 * Transisi ilegal ditolak di service-layer (konsisten dgn PembayaranService).
 *
 * Versioning: tiap pembuatan/revisi menyimpan snapshot ke dokumen_mutu_versi
 * (riwayat tak-bisa-diubah). `revisi()` menaikkan versi & mengembalikan ke draft.
 */
class MutuService
{
    /** Status approval yang sah. */
    public const STATUS = ['draft', 'diajukan', 'disetujui', 'ditolak'];

    public function buatDokumen(array $data): DokumenMutu
    {
        $this->ensureTenantContext();

        if (empty($data['judul']) || empty($data['jenis'])) {
            throw new InvalidArgumentException('Judul dan jenis dokumen wajib diisi.');
        }

        return DB::transaction(function () use ($data): DokumenMutu {
            $doc = DokumenMutu::create([
                'judul' => $data['judul'],
                'jenis' => $data['jenis'],
                'regulasi_acuan' => $data['regulasi_acuan'] ?? null,
                'berkas_url' => $data['berkas_url'] ?? null,
                'status_kelengkapan' => $data['status_kelengkapan'] ?? 'kurang',
                'status_approval' => 'draft',
                'versi' => 1,
                'created_by' => $data['created_by'] ?? null,
            ]);

            $this->snapshot($doc, $data['catatan'] ?? 'Versi awal', $data['created_by'] ?? null);

            return $doc;
        });
    }

    public function ajukan(DokumenMutu|int $dokumen, int $userId): DokumenMutu
    {
        $this->ensureTenantContext();
        $doc = $this->resolve($dokumen);

        if ($doc->status_approval !== 'draft') {
            throw new InvalidArgumentException("Hanya dokumen draft yang bisa diajukan (status: {$doc->status_approval}).");
        }

        $doc->update([
            'status_approval' => 'diajukan',
            'diajukan_oleh' => $userId,
            'diajukan_pada' => now(),
        ]);

        return $doc->fresh();
    }

    public function setujui(DokumenMutu|int $dokumen, int $reviewerId): DokumenMutu
    {
        $this->ensureTenantContext();
        $doc = $this->resolve($dokumen);

        if ($doc->status_approval !== 'diajukan') {
            throw new InvalidArgumentException("Hanya dokumen diajukan yang bisa disetujui (status: {$doc->status_approval}).");
        }

        $doc->update([
            'status_approval' => 'disetujui',
            'direview_oleh' => $reviewerId,
            'direview_pada' => now(),
            'catatan_review' => null,
        ]);

        return $doc->fresh();
    }

    public function tolak(DokumenMutu|int $dokumen, int $reviewerId, string $alasan): DokumenMutu
    {
        $this->ensureTenantContext();
        $doc = $this->resolve($dokumen);

        if ($doc->status_approval !== 'diajukan') {
            throw new InvalidArgumentException("Hanya dokumen diajukan yang bisa ditolak (status: {$doc->status_approval}).");
        }

        $doc->update([
            'status_approval' => 'ditolak',
            'direview_oleh' => $reviewerId,
            'direview_pada' => now(),
            'catatan_review' => $alasan,
        ]);

        return $doc->fresh();
    }

    /**
     * Buat versi baru (revisi): naikkan versi, kembalikan ke draft, simpan snapshot.
     * Boleh dari status mana pun (revisi dokumen yang sudah disetujui/ditolak).
     */
    public function revisi(DokumenMutu|int $dokumen, array $data, ?int $userId = null): DokumenMutu
    {
        $this->ensureTenantContext();
        $doc = $this->resolve($dokumen);

        return DB::transaction(function () use ($doc, $data, $userId): DokumenMutu {
            $doc->update([
                'judul' => $data['judul'] ?? $doc->judul,
                'jenis' => $data['jenis'] ?? $doc->jenis,
                'regulasi_acuan' => array_key_exists('regulasi_acuan', $data) ? $data['regulasi_acuan'] : $doc->regulasi_acuan,
                'berkas_url' => array_key_exists('berkas_url', $data) ? $data['berkas_url'] : $doc->berkas_url,
                'status_kelengkapan' => $data['status_kelengkapan'] ?? $doc->status_kelengkapan,
                'status_approval' => 'draft',
                'versi' => $doc->versi + 1,
                'diajukan_oleh' => null,
                'diajukan_pada' => null,
                'direview_oleh' => null,
                'direview_pada' => null,
                'catatan_review' => null,
            ]);

            $fresh = $doc->fresh();
            $this->snapshot($fresh, $data['catatan'] ?? ('Revisi versi '.$fresh->versi), $userId);

            return $fresh;
        });
    }

    /** Riwayat versi (snapshot) terurut. */
    public function riwayat(DokumenMutu|int $dokumen): Collection
    {
        $this->ensureTenantContext();

        return $this->resolve($dokumen)->versiRiwayat()->get();
    }

    private function snapshot(DokumenMutu $doc, ?string $catatan, ?int $userId): void
    {
        DokumenMutuVersi::create([
            'dokumen_mutu_id' => $doc->id,
            'versi' => $doc->versi,
            'judul' => $doc->judul,
            'jenis' => $doc->jenis,
            'regulasi_acuan' => $doc->regulasi_acuan,
            'berkas_url' => $doc->berkas_url,
            'status_approval' => $doc->status_approval,
            'catatan' => $catatan,
            'dibuat_oleh' => $userId,
        ]);
    }

    private function resolve(DokumenMutu|int $dokumen): DokumenMutu
    {
        return $dokumen instanceof DokumenMutu
            ? $dokumen
            : DokumenMutu::query()->findOrFail($dokumen);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
