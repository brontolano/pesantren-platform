<?php

namespace Modules\Kesantrian\Services;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Kesantrian\Models\Santri;

/**
 * Auto-NIS berformat standar (default ala Kemenag) — template per tenant via
 * Setting, counter RACE-SAFE meniru pola SuratService: lock baris urut
 * tertinggi (lockForUpdate) + create dalam SATU transaksi; unique(tenant,nis)
 * di DB sebagai backstop + retry sekali pada bentrok.
 *
 * Token template: {NSP} (kode lembaga/NSP dari Setting), {TA} (tahun masuk
 * 4 digit), {TA:2} (2 digit terakhir), {URUT:n} (counter zero-pad n digit).
 * Default: '{NSP}{TA:2}{URUT:4}' bila NSP terisi, fallback '{TA}.{URUT:4}'.
 */
class NisGeneratorService
{
    public const KEY_TEMPLATE = 'nis_template';

    public const KEY_RESET_SCOPE = 'nis_reset_scope'; // tahun_ajaran|global

    public const KEY_KODE_LEMBAGA = 'nis_kode_lembaga'; // NSP/NPSN

    /** Format NIS dari komponen (murni string, tanpa DB). */
    public function format(int $urut, int $tahunMasuk): string
    {
        $template = $this->template();
        $nsp = (string) (Setting::getTenantStringValue(self::KEY_KODE_LEMBAGA) ?? '');

        return (string) preg_replace_callback(
            '/\{(NSP|TA(?::(\d+))?|URUT:(\d+))\}/',
            function (array $m) use ($urut, $tahunMasuk, $nsp): string {
                if ($m[1] === 'NSP') {
                    return $nsp;
                }
                if (str_starts_with($m[1], 'TA')) {
                    $digits = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 4;

                    return substr((string) $tahunMasuk, -$digits);
                }

                // URUT:n
                return str_pad((string) $urut, (int) $m[3], '0', STR_PAD_LEFT);
            },
            $template,
        );
    }

    /**
     * Buat Santri BARU dengan NIS otomatis — transaksional & race-safe.
     * $data TANPA 'nis'/'nis_urut' (diisi service). Retry sekali bila unique
     * NIS bentrok (dua loket bersamaan).
     */
    public function createSantri(array $data, ?int $tahunMasuk = null): Santri
    {
        $tahun = $tahunMasuk ?? (int) now()->year;

        $coba = function () use ($data, $tahun): Santri {
            return DB::transaction(function () use ($data, $tahun): Santri {
                // Lock baris urut tertinggi pada scope (PG menolak FOR UPDATE
                // + agregat — pola sama dgn SuratService).
                $query = Santri::query()->whereNotNull('nis_urut');
                if ($this->resetScope() === 'tahun_ajaran') {
                    $query->where('tahun_angkatan', 'like', $tahun.'%');
                }
                $tertinggi = $query->orderByDesc('nis_urut')->lockForUpdate()->value('nis_urut');
                $urut = ((int) $tertinggi) + 1;

                return Santri::create($data + [
                    'nis' => $this->format($urut, $tahun),
                    'nis_urut' => $urut,
                    'tahun_angkatan' => $data['tahun_angkatan'] ?? ($tahun.'/'.($tahun + 1)),
                ]);
            });
        };

        try {
            return $coba();
        } catch (QueryException $e) {
            // Backstop unique(tenant,nis): bentrok antar-loket → coba sekali lagi.
            if (in_array((string) $e->getCode(), ['23505', '23000'], true)) {
                return $coba();
            }

            throw $e;
        }
    }

    /** Pratinjau NIS berikutnya (TIDAK mengunci — hanya tampilan loket). */
    public function preview(?int $tahunMasuk = null): string
    {
        $tahun = $tahunMasuk ?? (int) now()->year;
        $query = Santri::query()->whereNotNull('nis_urut');
        if ($this->resetScope() === 'tahun_ajaran') {
            $query->where('tahun_angkatan', 'like', $tahun.'%');
        }

        return $this->format(((int) $query->max('nis_urut')) + 1, $tahun);
    }

    private function template(): string
    {
        $custom = Setting::getTenantStringValue(self::KEY_TEMPLATE);
        if (is_string($custom) && trim($custom) !== '') {
            return trim($custom);
        }

        $nsp = (string) (Setting::getTenantStringValue(self::KEY_KODE_LEMBAGA) ?? '');

        // Default standar: ala Kemenag bila kode lembaga terisi.
        return $nsp !== '' ? '{NSP}{TA:2}{URUT:4}' : '{TA}.{URUT:4}';
    }

    private function resetScope(): string
    {
        $scope = Setting::getTenantStringValue(self::KEY_RESET_SCOPE);

        return $scope === 'global' ? 'global' : 'tahun_ajaran';
    }
}
