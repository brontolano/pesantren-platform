<?php

namespace Modules\Keuangan\Services;

use Modules\Keuangan\Models\SkemaBiaya;
use Modules\Keuangan\Models\Tagihan;
use Modules\Kesantrian\Models\Santri;
use RuntimeException;

/**
 * G2-16 — Pricing Rule. Resolusi tarif baku (skema_biaya) + generate Tagihan
 * lewat TagihanService (LIVE, anti-tumpang-tindih). Diskon syariat (keringanan
 * yatim/dhuafa) diteruskan sebagai diskon Tagihan.
 */
class SkemaBiayaService
{
    /** Tarif paling spesifik: cocok divisi > berlaku umum (divisi null). */
    public function resolve(string $tahun, ?int $divisiId, string $jenis): ?SkemaBiaya
    {
        return SkemaBiaya::query()
            ->where('tahun_ajaran', $tahun)
            ->where('jenis_tagihan', $jenis)
            ->where('aktif', true)
            ->where(fn ($q) => $q->where('divisi_id', $divisiId)->orWhereNull('divisi_id'))
            ->orderByRaw('divisi_id IS NULL') // false (spesifik) dulu, baru umum
            ->first();
    }

    public function nominalUntuk(string $tahun, ?int $divisiId, string $jenis): float
    {
        return (float) ($this->resolve($tahun, $divisiId, $jenis)?->nominal ?? 0);
    }

    /**
     * Generate Tagihan dari skema. Null bila tak ada skema cocok.
     *
     * @param  array{divisi_id?:?int, diskon?:float, jatuh_tempo?:?string, bulan_masehi?:?string, keterangan?:?string}  $opsi
     */
    public function generateTagihan(Santri $santri, string $tahun, string $jenis, array $opsi = []): ?Tagihan
    {
        $this->ensureTenantContext();

        $skema = $this->resolve($tahun, $opsi['divisi_id'] ?? null, $jenis);
        if ($skema === null) {
            return null;
        }

        $tagihan = app(TagihanService::class)->buatTagihanUntukSantri($santri, [
            'jenis_tagihan' => $jenis,
            'nominal' => (float) $skema->nominal,
            'diskon' => (float) ($opsi['diskon'] ?? 0),
            'periode_akademik' => $tahun,
            'jatuh_tempo' => $opsi['jatuh_tempo'] ?? null,
            'bulan_masehi' => $opsi['bulan_masehi'] ?? null,
            'keterangan' => $opsi['keterangan'] ?? $skema->keterangan,
        ]);

        // Dana penanggung dari skema (mis. SPP via swadaya) — sisi penagihan.
        if ($skema->sumber_dana_id) {
            $tagihan->update(['sumber_dana_id' => $skema->sumber_dana_id]);
        }

        return $tagihan->fresh();
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk skema biaya.');
        }
    }
}
