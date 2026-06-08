<?php

namespace Modules\Pajak\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Pajak\Models\TarifPajak;

/**
 * Seed tarif pajak default (PMK 59/2022) — idempoten. Nilai DAPAT diubah tiap tenant
 * dan WAJIB ditinjau penasihat pajak sebelum rilis (Gate kepatuhan Q3).
 */
class PajakSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            // kode, nama, tarif%, ambang_min, dasar
            ['ppn', 'PPN', 11.00, 1000000, 'PMK 59/2022 — dipungut bendahara belanja BOS/BOP > Rp1jt'],
            ['pph22', 'PPh Pasal 22 (barang)', 1.50, 2000000, 'PMK 59/2022 — non-BOS > Rp2jt ber-NPWP; barang BOS dikecualikan'],
            ['pph23_npwp', 'PPh Pasal 23 (jasa, NPWP)', 2.00, null, 'PMK 59/2022 — tanpa ambang minimum'],
            ['pph23_non_npwp', 'PPh Pasal 23 (jasa, non-NPWP)', 4.00, null, 'PMK 59/2022 — tanpa ambang minimum'],
            ['pph_final_42', 'PPh Final Pasal 4(2) — sewa', 10.00, null, 'PMK 59/2022 — sewa tanah/bangunan'],
        ];

        foreach ($data as [$kode, $nama, $tarif, $ambang, $dasar]) {
            TarifPajak::updateOrCreate(
                ['kode' => $kode],
                ['nama' => $nama, 'tarif_persen' => $tarif, 'ambang_min' => $ambang, 'dasar' => $dasar, 'is_active' => true],
            );
        }
    }
}
