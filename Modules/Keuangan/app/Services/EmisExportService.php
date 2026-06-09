<?php

namespace Modules\Keuangan\Services;

use Modules\Keuangan\Models\Tagihan;
use Modules\Kesantrian\Models\Santri;

/**
 * Generator EKSPOR TERFORMAT EMIS (BUKAN API real-time — tidak ada API publik EMIS).
 * Menghasilkan baris data santri + status keuangan untuk diunggah ke portal EMIS.
 */
class EmisExportService
{
    /** Header kolom (skema sederhana mengikuti taksonomi EMIS). */
    public const HEADER = ['NISN', 'NIS', 'NAMA', 'JENIS_KELAMIN', 'TAHUN_ANGKATAN', 'STATUS_SANTRI', 'STATUS_SPP', 'TUNGGAKAN'];

    /**
     * @return array<int, array<int, string|float>>
     */
    public function barisSantri(): array
    {
        return Santri::query()->orderBy('nama')->get()->map(function (Santri $s) {
            $tunggakan = Tagihan::where('santri_id', $s->id)->belumLunas()->get()
                ->sum(fn (Tagihan $t) => $t->sisa_tagihan);

            return [
                $s->nisn ?? '',
                $s->nis ?? '',
                $s->nama,
                $s->jenis_kelamin ?? '',
                $s->tahun_angkatan ?? '',
                $s->status ?? '',
                $tunggakan > 0 ? 'MENUNGGAK' : 'LUNAS',
                round((float) $tunggakan, 2),
            ];
        })->all();
    }
}
