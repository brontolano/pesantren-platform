<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;

class KesantrianOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $santriAktif = Santri::where('status', 'aktif')->count();
        $santriCuti = Santri::where('status', 'cuti')->count();
        $totalKelas = Kelas::where('is_active', true)->count();
        $totalAlumni = Santri::where('status', 'lulus')->count();

        return [
            Stat::make('Santri Aktif', $santriAktif)
                ->description('Santri berstatus aktif')
                ->color('success')
                ->icon('heroicon-o-user-group'),
            Stat::make('Santri Cuti', $santriCuti)
                ->description('Santri berstatus cuti')
                ->color('warning')
                ->icon('heroicon-o-clock'),
            Stat::make('Jumlah Kelas', $totalKelas)
                ->description('Kelas aktif')
                ->color('info')
                ->icon('heroicon-o-academic-cap'),
            Stat::make('Total Alumni', $totalAlumni)
                ->description('Santri yang telah lulus')
                ->color('primary')
                ->icon('heroicon-o-trophy'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false;
    }
}
