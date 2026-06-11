<?php

namespace Modules\Akademik\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Modules\Akademik\Models\KalenderAkademik;
use Modules\Akademik\Models\Lembaga;

/**
 * WP-02 — Mesin hari efektif (Blueprint Akademik A-0 poin 3).
 *
 * - Entri jenis libur_nasional|libur_pondok|ramadhan MENGURANGI hari efektif;
 *   ujian|pasaran|kegiatan TIDAK (hanya penanda agenda).
 * - lembaga_id NULL = berlaku semua lembaga; entri ber-lembaga hanya berlaku
 *   untuk lembaga itu (formal tetap masuk saat diniyah libur Ramadhan).
 * - Hari libur mingguan = TOMBOL KEBIJAKAN per tenant via Setting
 *   `akademik.hari_libur_mingguan` (default ['minggu']) — banyak pesantren
 *   libur Jumat, BUKAN Minggu.
 */
class KalenderService
{
    public const SETTING_HARI_LIBUR_MINGGUAN = 'akademik.hari_libur_mingguan';

    public const DEFAULT_HARI_LIBUR_MINGGUAN = ['minggu'];

    /** Nama hari (id) → Carbon dayOfWeek (0=Minggu .. 6=Sabtu). */
    private const HARI = [
        'minggu' => 0, 'senin' => 1, 'selasa' => 2, 'rabu' => 3,
        'kamis' => 4, 'jumat' => 5, 'sabtu' => 6,
    ];

    /**
     * Apakah $tanggal hari efektif (tidak jatuh dalam entri libur yang
     * berlaku)? Hari libur mingguan TIDAK dicek di sini — gunakan
     * hariEfektifDalamRentang() untuk perhitungan kalender penuh.
     */
    public function isHariEfektif(Carbon $tanggal, ?Lembaga $lembaga = null): bool
    {
        return ! KalenderAkademik::query()
            ->whereIn('jenis', KalenderAkademik::JENIS_TIDAK_EFEKTIF)
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->where(fn (Builder $q) => $this->scopeLembaga($q, $lembaga))
            ->exists();
    }

    /**
     * Jumlah hari efektif dalam rentang [$dari, $sampai] inklusif:
     * dikurangi hari libur mingguan (kebijakan tenant) + hari yang jatuh
     * dalam entri libur yang berlaku. Satu query ambil entri overlap,
     * hitung di memori — TIDAK query per hari.
     */
    public function hariEfektifDalamRentang(Carbon $dari, Carbon $sampai, ?Lembaga $lembaga = null): int
    {
        $dari = $dari->copy()->startOfDay();
        $sampai = $sampai->copy()->startOfDay();

        if ($sampai->lt($dari)) {
            return 0;
        }

        $entri = KalenderAkademik::query()
            ->whereIn('jenis', KalenderAkademik::JENIS_TIDAK_EFEKTIF)
            ->whereDate('tanggal_mulai', '<=', $sampai)
            ->whereDate('tanggal_selesai', '>=', $dari)
            ->where(fn (Builder $q) => $this->scopeLembaga($q, $lembaga))
            ->get(['tanggal_mulai', 'tanggal_selesai']);

        $liburMingguan = $this->hariLiburMingguan();

        $efektif = 0;
        for ($hari = $dari->copy(); $hari->lte($sampai); $hari->addDay()) {
            if (in_array($hari->dayOfWeek, $liburMingguan, true)) {
                continue;
            }

            foreach ($entri as $e) {
                if ($hari->betweenIncluded($e->tanggal_mulai, $e->tanggal_selesai)) {
                    continue 2;
                }
            }

            $efektif++;
        }

        return $efektif;
    }

    /** @return int[] dayOfWeek (0-6) hari libur mingguan dari kebijakan tenant. */
    private function hariLiburMingguan(): array
    {
        $setting = Setting::getTenantValue(self::SETTING_HARI_LIBUR_MINGGUAN, self::DEFAULT_HARI_LIBUR_MINGGUAN);

        // Toleran bentuk simpanan: array (cast normal), string tunggal
        // ('jumat'), atau string JSON ('["jumat"]') dari sumber lain.
        if (is_string($setting)) {
            $decoded = json_decode($setting, true);
            $setting = is_array($decoded) ? $decoded : [$setting];
        }

        if (! is_array($setting)) {
            $setting = self::DEFAULT_HARI_LIBUR_MINGGUAN;
        }

        // Array kosong [] = kebijakan SAH "tanpa libur mingguan" (mengaji
        // tiap hari). Nama tak dikenal (typo) di-skip — tapi bila SEMUA
        // entri non-kosong tak dikenal, jatuh ke default agar typo tidak
        // diam-diam menghapus libur mingguan.
        $hari = [];
        foreach ($setting as $nama) {
            $key = strtolower(trim((string) $nama));
            if (isset(self::HARI[$key])) {
                $hari[] = self::HARI[$key];
            }
        }

        if ($hari === [] && $setting !== []) {
            return array_map(fn (string $n): int => self::HARI[$n], self::DEFAULT_HARI_LIBUR_MINGGUAN);
        }

        return array_values(array_unique($hari));
    }

    /** Entri berlaku: NULL-lembaga (semua) ATAU lembaga yang sama. */
    private function scopeLembaga(Builder $q, ?Lembaga $lembaga): void
    {
        $q->whereNull('lembaga_id');

        if ($lembaga !== null) {
            $q->orWhere('lembaga_id', $lembaga->getKey());
        }
    }
}
