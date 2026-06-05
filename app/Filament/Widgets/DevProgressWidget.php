<?php

namespace App\Filament\Widgets;

use App\Models\DevTask;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DevProgressWidget extends BaseWidget
{
    protected static ?int $sort = 20;

    protected static bool $isLazy = true;

    protected ?string $heading = 'Progres Pengembangan';

    protected function getStats(): array
    {
        $total = DevTask::query()->count();
        $done = DevTask::query()->where('status', 'done')->count();
        $inProgress = DevTask::query()->where('status', 'in_progress')->count();
        $blocked = DevTask::query()->where('status', 'blocked')->count();
        $overall = $total > 0 ? (int) round((DevTask::query()->sum('progress')) / $total) : 0;

        return [
            Stat::make('Progres Keseluruhan', $overall.'%')
                ->description($done.' dari '.$total.' task selesai')
                ->color($overall >= 100 ? 'success' : ($overall >= 50 ? 'info' : 'warning'))
                ->icon('heroicon-o-chart-pie'),

            Stat::make('Dikerjakan', $inProgress)
                ->description('Sedang berjalan')
                ->color('info')
                ->icon('heroicon-o-bolt'),

            Stat::make('Selesai', $done)
                ->description('Task rampung')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Terblokir', $blocked)
                ->description($blocked > 0 ? 'Perlu perhatian' : 'Tidak ada')
                ->color($blocked > 0 ? 'danger' : 'gray')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}
