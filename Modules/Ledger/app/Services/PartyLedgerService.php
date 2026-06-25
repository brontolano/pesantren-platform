<?php

namespace Modules\Ledger\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Ledger\Models\JurnalBaris;

/**
 * F4 — Buku besar pembantu per pihak (party). Saldo & aging piutang/utang
 * dari baris jurnal ber-party (di-tag pada baris piutang/utang).
 * Saldo > 0 = piutang (pihak berutang ke pesantren); < 0 = utang (pesantren berutang).
 * Modul consumer cukup men-tag party_type/party_id pada baris jurnal terkait.
 */
class PartyLedgerService
{
    /** Saldo bersih (debit − kredit) untuk satu pihak dalam periode. */
    public function saldo(string $partyType, int $partyId, ?string $from = null, ?string $to = null): float
    {
        $q = JurnalBaris::query()
            ->where('party_type', $partyType)
            ->where('party_id', $partyId)
            ->whereHas('jurnal', fn ($j) => $this->scopePosted($j, $from, $to));

        return round((float) $q->sum('debit') - (float) $q->sum('kredit'), 2);
    }

    /**
     * Daftar pihak (party_id → saldo) bersaldo ≠ 0 untuk satu tipe.
     *
     * @return array<int, float>
     */
    public function daftarByTipe(string $partyType, ?string $from = null, ?string $to = null): array
    {
        $rows = JurnalBaris::query()
            ->where('party_type', $partyType)
            ->whereNotNull('party_id')
            ->whereHas('jurnal', fn ($j) => $this->scopePosted($j, $from, $to))
            ->get(['party_id', 'debit', 'kredit']);

        $agg = [];
        foreach ($rows as $r) {
            $agg[$r->party_id] = ($agg[$r->party_id] ?? 0.0) + (float) $r->debit - (float) $r->kredit;
        }

        return collect($agg)
            ->map(fn ($v) => round($v, 2))
            ->filter(fn ($v) => abs($v) >= 0.005)
            ->all();
    }

    /**
     * Aging saldo pihak per umur baris jurnal terhadap $asOf.
     *
     * @return array{'0-30':float,'31-60':float,'61-90':float,'>90':float}
     */
    public function aging(string $partyType, int $partyId, ?string $asOf = null): array
    {
        $asOf = $asOf ? Carbon::parse($asOf) : now();

        $rows = JurnalBaris::query()
            ->where('party_type', $partyType)
            ->where('party_id', $partyId)
            ->whereHas('jurnal', fn ($j) => $j->where('status', 'posted'))
            ->with('jurnal:id,tanggal')
            ->get(['id', 'jurnal_id', 'debit', 'kredit']);

        $bucket = ['0-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '>90' => 0.0];

        foreach ($rows as $r) {
            $net = (float) $r->debit - (float) $r->kredit;
            $umur = $r->jurnal?->tanggal ? Carbon::parse($r->jurnal->tanggal)->diffInDays($asOf) : 0;
            $key = $umur <= 30 ? '0-30' : ($umur <= 60 ? '31-60' : ($umur <= 90 ? '61-90' : '>90'));
            $bucket[$key] += $net;
        }

        return array_map(fn ($v) => round($v, 2), $bucket);
    }

    /**
     * G3-23 — Mutasi (buku pembantu) satu pihak: daftar baris jurnal posted
     * dengan info jurnal, terurut tanggal. Saldo berjalan dihitung pemanggil.
     *
     * @return Collection<int, array{tanggal:?string, nomor:?string, deskripsi:?string, debit:float, kredit:float, mutasi:float}>
     */
    public function mutasi(string $partyType, int $partyId, ?string $from = null, ?string $to = null): Collection
    {
        return JurnalBaris::query()
            ->where('party_type', $partyType)
            ->where('party_id', $partyId)
            ->whereHas('jurnal', fn ($j) => $this->scopePosted($j, $from, $to))
            ->with('jurnal:id,nomor,tanggal,deskripsi')
            ->get()
            ->sortBy(fn (JurnalBaris $b) => $b->jurnal?->tanggal)
            ->map(fn (JurnalBaris $b): array => [
                'tanggal' => $b->jurnal?->tanggal ? (string) $b->jurnal->tanggal : null,
                'nomor' => $b->jurnal?->nomor,
                'deskripsi' => $b->jurnal?->deskripsi,
                'debit' => round((float) $b->debit, 2),
                'kredit' => round((float) $b->kredit, 2),
                'mutasi' => round((float) $b->debit - (float) $b->kredit, 2),
            ])
            ->values();
    }

    private function scopePosted($j, ?string $from, ?string $to): void
    {
        $j->where('status', 'posted');
        if ($from) {
            $j->whereDate('tanggal', '>=', $from);
        }
        if ($to) {
            $j->whereDate('tanggal', '<=', $to);
        }
    }
}
