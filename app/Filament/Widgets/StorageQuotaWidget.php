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
        $used = $svc->usedBytes();
        $quotaBytes = $svc->quotaBytes($tenantId);
        $pct = $svc->percentUsed($tenantId);
        $remaining = max(0, $quotaBytes - $used);

        return [
            Stat::make('Kuota', $svc->quotaGb($tenantId).' GB')
                ->description('Total penyimpanan')
                ->color('gray')
                ->icon('heroicon-o-circle-stack'),

            Stat::make('Terpakai', StorageQuotaService::humanBytes($used))
                ->description($pct.'% terpakai')
                ->color($pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success'))
                ->icon('heroicon-o-chart-pie'),

            Stat::make('Sisa', StorageQuotaService::humanBytes($remaining))
                ->description('Ruang tersedia')
                ->color('info')
                ->icon('heroicon-o-inbox'),
        ];
    }
}
