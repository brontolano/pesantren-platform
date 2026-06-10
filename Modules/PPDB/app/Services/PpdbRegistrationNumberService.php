<?php

namespace Modules\PPDB\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\PPDB\Models\CalonSantri;
use RuntimeException;

/**
 * Nomor pendaftaran PPDB-{tahun}-{urut3} — race-safe meniru pola SuratService:
 * lock baris nomor tertinggi + INSERT CalonSantri dalam SATU transaksi, dengan
 * unique (tenant_id, nomor_pendaftaran) sebagai backstop dan retry SEKALI saat
 * tabrakan (dua pendaftar submit bersamaan).
 */
class PpdbRegistrationNumberService
{
    /**
     * Buat CalonSantri dengan nomor pendaftaran race-safe.
     * $attributes TANPA 'nomor_pendaftaran' — diisi oleh service ini.
     */
    public function createWithNomor(array $attributes, ?int $year = null): CalonSantri
    {
        $this->ensureTenantContext();

        $targetYear = $year ?? (int) now()->format('Y');
        $prefix = sprintf('PPDB-%d-', $targetYear);

        $simpan = function () use ($attributes, $prefix): CalonSantri {
            return DB::transaction(function () use ($attributes, $prefix): CalonSantri {
                return CalonSantri::create(
                    $attributes + ['nomor_pendaftaran' => $this->nomorBerikutnya($prefix)],
                );
            });
        };

        try {
            return $simpan();
        } catch (QueryException $e) {
            // Tabrakan nomor (unique violation) → ulangi sekali dengan nomor baru.
            if (in_array((string) $e->getCode(), ['23505', '23000'], true)) {
                return $simpan();
            }

            throw $e;
        }
    }

    /**
     * Hitung nomor berikutnya — HARUS dipanggil di dalam transaksi agar lock
     * bertahan sampai insert. Protected sebagai seam untuk uji tabrakan.
     */
    protected function nomorBerikutnya(string $prefix): string
    {
        $lastNumber = CalonSantri::query()
            ->where('nomor_pendaftaran', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('nomor_pendaftaran')
            ->value('nomor_pendaftaran');

        return sprintf('%s%03d', $prefix, $this->extractNextSequence($lastNumber));
    }

    /**
     * @deprecated TIDAK race-safe: lock dilepas saat transaksi ini selesai,
     * SEBELUM pemanggil insert — pakai createWithNomor(). Dipertahankan hanya
     * untuk kompatibilitas (preview/pemanggil lama).
     */
    public function generate(?int $year = null): string
    {
        $this->ensureTenantContext();

        $targetYear = $year ?? (int) now()->format('Y');
        $prefix = sprintf('PPDB-%d-', $targetYear);

        return DB::transaction(fn (): string => $this->nomorBerikutnya($prefix));
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk generate nomor pendaftaran PPDB.');
        }
    }

    private function extractNextSequence(?string $lastNumber): int
    {
        if (! is_string($lastNumber) || $lastNumber === '') {
            return 1;
        }

        $parts = explode('-', $lastNumber);
        $lastSequence = (int) end($parts);

        return $lastSequence + 1;
    }
}
