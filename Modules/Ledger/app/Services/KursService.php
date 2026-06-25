<?php

namespace Modules\Ledger\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Models\KursValuta;
use RuntimeException;

/**
 * G3-23 — Multi-currency RINGAN (donor asing). Konversi ke IDR saat input;
 * posting Buku Besar SELALU IDR (math teruji tak diubah). Nilai asing/kurs
 * dicatat sebagai metadata jurnal (record-only).
 */
class KursService
{
    public function __construct(private readonly JurnalService $jurnalService) {}

    /** Set/update kurs untuk satu mata uang pada satu tanggal berlaku. */
    public function setKurs(string $mataUang, float $kurs, ?string $tanggal = null): KursValuta
    {
        $this->ensureTenantContext();
        if ($kurs <= 0) {
            throw new InvalidArgumentException('Kurs harus lebih dari 0.');
        }
        $tanggal = $tanggal ?? Carbon::now()->toDateString();

        return KursValuta::updateOrCreate(
            ['mata_uang' => strtoupper($mataUang), 'berlaku_tanggal' => $tanggal],
            ['kurs' => round($kurs, 4), 'is_active' => true],
        );
    }

    /** Kurs terkini (≤ tanggal) untuk mata uang; null bila belum diset. */
    public function kursTerkini(string $mataUang, ?string $tanggal = null): ?float
    {
        $this->ensureTenantContext();
        $tanggal = $tanggal ?? Carbon::now()->toDateString();

        $kurs = KursValuta::query()
            ->where('mata_uang', strtoupper($mataUang))
            ->where('is_active', true)
            ->whereDate('berlaku_tanggal', '<=', $tanggal)
            ->orderByDesc('berlaku_tanggal')
            ->orderByDesc('id') // tie-breaker deterministik bila ada >1 baris tanggal sama
            ->value('kurs');

        return $kurs !== null ? (float) $kurs : null;
    }

    /** Konversi nominal valuta asing ke IDR (round 2). */
    public function konversiKeIdr(float $nominalAsing, string $mataUang, ?string $tanggal = null): float
    {
        $kurs = $this->kursTerkini($mataUang, $tanggal);
        if ($kurs === null) {
            throw new InvalidArgumentException("Kurs {$mataUang} belum diset.");
        }

        return round($nominalAsing * $kurs, 2);
    }

    /**
     * Catat penerimaan donor valas: posting IDR (D Kas/Bank, K Pendapatan) +
     * metadata valuta pada jurnal. Posting via JurnalService (seimbang, IDR).
     */
    public function catatPenerimaanDonor(Akun|int $akunKas, Akun|int $akunPendapatan, float $nominalAsing, string $mataUang, array $opts = []): Jurnal
    {
        $this->ensureTenantContext();
        $kas = $akunKas instanceof Akun ? $akunKas : Akun::query()->findOrFail($akunKas);
        $pendapatan = $akunPendapatan instanceof Akun ? $akunPendapatan : Akun::query()->findOrFail($akunPendapatan);
        if ($nominalAsing <= 0) {
            throw new InvalidArgumentException('Nominal asing harus lebih dari 0.');
        }

        $tanggal = $opts['tanggal'] ?? Carbon::now()->toDateString();
        $kurs = $this->kursTerkini($mataUang, $tanggal);
        if ($kurs === null) {
            throw new InvalidArgumentException("Kurs {$mataUang} belum diset.");
        }
        $idr = round($nominalAsing * $kurs, 2);

        // Idempotensi WAJIB: jangan biarkan ref null (deriveIdempotencyKey balik null →
        // unique(tenant_id, idempotency_key) tak men-dedup NULL → submit ulang double-post).
        // Anchor ke dokumen sumber bila diberi; selain itu turunkan kunci konten yang stabil.
        $refType = $opts['ref_type'] ?? 'donor_valas';
        $refId = $opts['ref_id'] ?? substr(sha1(implode('|', [
            $kas->id, $pendapatan->id, $tanggal, strtoupper($mataUang),
            number_format($nominalAsing, 2, '.', ''),
            $opts['sumber_dana_id'] ?? '',
        ])), 0, 32);

        return DB::transaction(function () use ($kas, $pendapatan, $idr, $nominalAsing, $kurs, $mataUang, $tanggal, $refType, $refId, $opts): Jurnal {
            $jurnal = $this->jurnalService->postJurnal([
                'tanggal' => Carbon::parse($tanggal),
                'deskripsi' => $opts['keterangan'] ?? ('Penerimaan donor '.strtoupper($mataUang)),
                'tipe_sumber' => 'donor_valas',
                'ref_type' => $refType,
                'ref_id' => $refId,
                'dibuat_oleh' => $opts['dibuat_oleh'] ?? null,
                'baris' => [
                    ['akun_id' => $kas->id, 'sumber_dana_id' => $opts['sumber_dana_id'] ?? null, 'debit' => $idr, 'kredit' => 0, 'memo' => 'Penerimaan donor (IDR)'],
                    ['akun_id' => $pendapatan->id, 'sumber_dana_id' => $opts['sumber_dana_id'] ?? null, 'debit' => 0, 'kredit' => $idr, 'memo' => 'Pendapatan donor'],
                ],
            ]);

            // Metadata valuta (record-only; tak mengubah nilai posting IDR).
            $jurnal->update(['mata_uang' => strtoupper($mataUang), 'kurs' => round($kurs, 4), 'nilai_asing' => round($nominalAsing, 2)]);

            return $jurnal->fresh();
        });
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
