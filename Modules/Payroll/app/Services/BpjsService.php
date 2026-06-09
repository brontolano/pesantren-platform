<?php

namespace Modules\Payroll\Services;

use App\Models\Setting;

/**
 * G2-12 — Potongan BPJS porsi PEKERJA (JKN/JHT/JP). Tarif konfigurabel via
 * Setting (default UU/PP). Catatan syariah: untuk lembaga yang menolak akad
 * konvensional, BPJS dapat diperlakukan via akad wakalah/ta'awun — kebijakan
 * tenant. KALKULATOR MURNI.
 *
 * ⚠️ Tarif & batas upah WAJIB ditinjau (Q3) — versi ini tanpa cap upah JP.
 */
class BpjsService
{
    /** Default persentase potongan PEKERJA. */
    private const DEFAULT = ['kesehatan' => 1.0, 'jht' => 2.0, 'jp' => 1.0];

    /** Persentase potongan pekerja untuk satu komponen. */
    public function rate(string $komponen): float
    {
        $v = Setting::getTenantValue('keuangan.bpjs.'.$komponen.'_pekerja');
        $v = is_array($v) ? ($v['value'] ?? null) : $v;

        return $v !== null ? (float) $v : (self::DEFAULT[$komponen] ?? 0.0);
    }

    public function totalRatePekerja(): float
    {
        return round($this->rate('kesehatan') + $this->rate('jht') + $this->rate('jp'), 2);
    }

    /** Potongan BPJS pekerja dari basis (honor). */
    public function potonganPekerja(float $basis): float
    {
        return round($basis * $this->totalRatePekerja() / 100, 2);
    }

    /** Rincian per komponen. */
    public function rincian(float $basis): array
    {
        return [
            'kesehatan' => round($basis * $this->rate('kesehatan') / 100, 2),
            'jht' => round($basis * $this->rate('jht') / 100, 2),
            'jp' => round($basis * $this->rate('jp') / 100, 2),
            'total' => $this->potonganPekerja($basis),
        ];
    }
}
