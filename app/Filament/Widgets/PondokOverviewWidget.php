<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Akademik\Models\Lembaga;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;

/**
 * Indikator ringkas per Pondok/Lembaga (mis. Pondok Banin & Pondok Banat di
 * asy-syifaa): jumlah santri aktif + jumlah kelas tiap pondok. Tampil HANYA bila
 * tenant memang memisah kepengurusan ke ≥2 lembaga jenis 'pondok'.
 */
class PondokOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0; // di bawah StatsPesantren (-8), atas chart

    protected ?string $heading = 'Per Pondok / Lembaga';

    public static function canView(): bool
    {
        if (! (auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false)) {
            return false;
        }

        try {
            return Lembaga::where('jenis', 'pondok')->count() >= 2;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function getStats(): array
    {
        $pondoks = Lembaga::where('jenis', 'pondok')
            ->orderBy('nama')
            ->get(['id', 'nama']);

        return $pondoks->map(function (Lembaga $pondok): Stat {
            $jmlSantri = Santri::where('status', 'aktif')
                ->whereHas('kelas', fn ($q) => $q->where('lembaga_id', $pondok->id))
                ->count();

            $jmlKelas = Kelas::where('lembaga_id', $pondok->id)->count();

            // Banat (perempuan) → rose; Banin / lainnya → emerald.
            $isBanat = str_contains(strtolower($pondok->nama), 'banat')
                || str_contains(strtolower($pondok->nama), 'putri');

            return Stat::make($pondok->nama, number_format($jmlSantri, 0, ',', '.').' santri')
                ->description($jmlKelas.' kelas aktif')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color($isBanat ? 'danger' : 'success')
                ->icon($isBanat ? 'heroicon-o-home-modern' : 'heroicon-o-building-library');
        })->all();
    }
}
