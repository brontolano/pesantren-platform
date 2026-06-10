<?php

namespace Modules\AI\Services\Escalation;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\AI\Models\AiEscalation;

/**
 * T-A023 — laporan bulanan eskalasi "Tanya Ustadz" untuk kyai/pimpinan
 * (PRD §7.6): volume per kategori, rata-rata waktu jawab, contoh ANONIM.
 *
 * Contoh anonim = pertanyaan_ringkas yang SUDAH terredaksi PII sejak ditulis
 * PolicyEngine (redactPaksa, T-A020) — kelas ini tidak menambah redaksi dan
 * TIDAK pernah membuka data lain. Dipakai perintah terjadwal
 * ai:laporan-eskalasi dan section laporan halaman EskalasiTanyaUstadz.
 */
class LaporanEskalasi
{
    /**
     * Agregat satu bulan kalender (berdasar created_at eskalasi, jam aplikasi).
     *
     * @return array{
     *     periode: string,
     *     total: int,
     *     volume_per_kategori: array<string, int>,
     *     per_status: array<string, int>,
     *     jumlah_dijawab: int,
     *     rata_rata_jawab_jam: float|null,
     *     contoh_anonim: list<string>,
     * }
     */
    public function bulanan(CarbonInterface|string $bulan): array
    {
        $awal = Carbon::parse($bulan)->startOfMonth();
        $akhir = $awal->copy()->endOfMonth();

        $eskalasi = AiEscalation::query()
            ->whereBetween('created_at', [$awal, $akhir])
            ->orderByDesc('created_at')
            ->get([
                'id', 'kategori', 'status', 'kanal', 'pertanyaan_ringkas',
                'created_at', 'dijawab_pada',
            ]);

        $volumeKategori = $eskalasi
            ->countBy('kategori')
            ->sortDesc()
            ->all();

        $perStatus = $eskalasi
            ->countBy('status')
            ->all();

        // Rata-rata waktu jawab (jam, 1 desimal) — hanya yang sudah dijawab.
        $durasiJam = $eskalasi
            ->filter(fn (AiEscalation $e): bool => $e->dijawab_pada !== null && $e->created_at !== null)
            ->map(fn (AiEscalation $e): float => $e->created_at->diffInSeconds($e->dijawab_pada) / 3600);

        $maksContoh = max(1, (int) config('ai.eskalasi.laporan_contoh_max', 5));

        $contoh = $eskalasi
            ->pluck('pertanyaan_ringkas')
            ->map(static fn ($p): string => trim((string) $p))
            ->filter(static fn (string $p): bool => $p !== '')
            ->unique()
            ->take($maksContoh)
            ->values()
            ->all();

        return [
            'periode' => $awal->format('Y-m'),
            'total' => $eskalasi->count(),
            'volume_per_kategori' => $volumeKategori,
            'per_status' => $perStatus,
            'jumlah_dijawab' => $durasiJam->count(),
            'rata_rata_jawab_jam' => $durasiJam->isEmpty()
                ? null
                : round((float) $durasiJam->avg(), 1),
            'contoh_anonim' => $contoh,
        ];
    }
}
