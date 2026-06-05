<?php

namespace App\Filament\Widgets;

use App\Services\StorageQuotaService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StorageQuotaWidget extends BaseWidget
{
    protected ?string $heading = 'Penyimpanan';

    protected function getStats(): array
    {
        $tenantId = tenant()?->getKey();
        if (! $tenantId) {
            return [];
        }

        $svc = app(StorageQuotaService::class);
        $b = $svc->breakdown();
        $used = (int) $b['footprint_bytes']; // file disk + DB tenant (footprint riil)
        $quotaBytes = $svc->quotaBytes($tenantId);
        $pct = $quotaBytes > 0 ? min(100.0, round($used / $quotaBytes * 100, 1)) : 100.0;
        $remaining = max(0, $quotaBytes - $used);
        $h = fn (int $x) => StorageQuotaService::humanBytes($x);

        return [
            Stat::make('Kuota', $svc->quotaGb($tenantId).' GB')
                ->description('Total penyimpanan')
                ->color('gray')
                ->icon('heroicon-o-circle-stack'),

            Stat::make('Terpakai', $h($used))
                ->description('File '.$h((int) $b['disk_bytes']).' + DB '.$h((int) $b['db_bytes']).' · '.$pct.'%')
                ->color($pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success'))
                ->icon('heroicon-o-chart-pie'),

            Stat::make('Sisa', StorageQuotaService::humanBytes($remaining))
                ->description('Ruang tersedia')
                ->color('info')
                ->icon('heroicon-o-inbox'),
        ];
    }
}
