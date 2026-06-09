<?php

namespace Modules\Akademik\Services;

use App\Models\Setting;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use Modules\Akademik\Models\PresensiSantri;
use Modules\Kesantrian\Models\Santri;
use RuntimeException;

/**
 * G3-18 — Presensi kelas harian santri + rekap kehadiran & threshold rapor.
 * Idempoten per (santri, tanggal) — catat ulang meng-update status hari itu.
 */
class PresensiSantriService
{
    /** Ambang kehadiran minimal default (%). */
    public const DEFAULT_AMBANG = 80;

    public function catat(Santri|int $santri, string|CarbonInterface $tanggal, string $status, array $opts = []): PresensiSantri
    {
        $this->ensureTenantContext();
        $santri = $santri instanceof Santri ? $santri : Santri::query()->findOrFail($santri);
        if (! in_array($status, PresensiSantri::STATUS, true)) {
            throw new InvalidArgumentException('Status presensi tidak valid.');
        }

        return PresensiSantri::updateOrCreate(
            ['santri_id' => $santri->id, 'tanggal' => $tanggal],
            [
                'kelas_id' => $opts['kelas_id'] ?? $santri->kelas_id,
                'status' => $status,
                'keterangan' => $opts['keterangan'] ?? null,
                'dicatat_oleh' => $opts['dicatat_oleh'] ?? null,
            ],
        );
    }

    /**
     * Rekap kehadiran santri pada rentang tanggal (inklusif).
     *
     * @return array{hadir:int, total:int, persen:float, per_status:array<string,int>}
     */
    public function rekap(Santri|int $santri, string|CarbonInterface $dari, string|CarbonInterface $sampai): array
    {
        $this->ensureTenantContext();
        $santriId = $santri instanceof Santri ? $santri->id : (int) $santri;

        $rows = PresensiSantri::where('santri_id', $santriId)
            ->whereBetween('tanggal', [$dari, $sampai])
            ->get();

        $total = $rows->count();
        $perStatus = [];
        foreach (PresensiSantri::STATUS as $s) {
            $perStatus[$s] = $rows->where('status', $s)->count();
        }
        $hadir = $rows->whereIn('status', PresensiSantri::HADIR_EFEKTIF)->count();
        $persen = $total > 0 ? round($hadir / $total * 100, 2) : 0.0;

        return ['hadir' => $hadir, 'total' => $total, 'persen' => $persen, 'per_status' => $perStatus];
    }

    /** Ambang kehadiran minimal (Setting tenant, fallback default). */
    public function ambang(): int
    {
        // Setting value bisa skalar ATAU dibungkus array (pola ['value'=>..]);
        // unwrap dulu agar override tenant tidak diabaikan diam-diam.
        $raw = Setting::getTenantValue('akademik_kehadiran_minimal', null);
        $value = is_array($raw) ? ($raw['value'] ?? $raw['text'] ?? null) : $raw;
        $ambang = is_numeric($value) ? (int) $value : self::DEFAULT_AMBANG;

        return ($ambang >= 0 && $ambang <= 100) ? $ambang : self::DEFAULT_AMBANG;
    }

    /** Apakah kehadiran santri memenuhi ambang minimal pada rentang. */
    public function memenuhiThreshold(Santri|int $santri, string|CarbonInterface $dari, string|CarbonInterface $sampai): bool
    {
        $rekap = $this->rekap($santri, $dari, $sampai);

        // Tanpa data presensi → tak bisa diklaim memenuhi (konservatif).
        if ($rekap['total'] === 0) {
            return false;
        }

        return $rekap['persen'] >= $this->ambang();
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
