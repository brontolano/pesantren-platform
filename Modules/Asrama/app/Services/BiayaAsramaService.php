<?php

namespace Modules\Asrama\Services;

use Modules\Asrama\Models\BiayaAsrama;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Services\TagihanService;

class BiayaAsramaService
{
    public function sinkronkan(array $data, ?BiayaAsrama $biaya = null): BiayaAsrama
    {
        $santri = Santri::query()->findOrFail($data['santri_id']);
        $kamar = isset($data['kamar_id']) ? KamarAsrama::query()->findOrFail($data['kamar_id']) : null;

        $tagihan = app(TagihanService::class)->sinkronkanTagihanUntukSantri(
            $santri,
            [
                'jenis_tagihan' => 'asrama',
                'keterangan' => $data['keterangan'] ?? 'Biaya asrama ' . $data['periode'],
                'nominal' => $data['nominal'],
                'jatuh_tempo' => $data['jatuh_tempo'] ?? now()->toDateString(),
                'status' => $data['status_pembayaran'] ?? 'belum_bayar',
                'bulan_masehi' => $data['periode'],
            ],
            $biaya?->tagihanKeuangan,
        );

        $record = $biaya ?? new BiayaAsrama();
        $record->fill([
            'santri_id' => $santri->id,
            'kamar_id' => $kamar?->id,
            'periode' => $data['periode'],
            'nominal' => $data['nominal'],
            'status_pembayaran' => $data['status_pembayaran'] ?? 'belum_bayar',
            'keuangan_tagihan_id' => $tagihan->id,
            'jatuh_tempo' => $data['jatuh_tempo'] ?? now()->toDateString(),
        ]);
        $record->save();

        return $record->fresh();
    }
}
