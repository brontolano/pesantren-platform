<?php

namespace App\Filament\Widgets;

use App\Services\BillingService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BillingOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 10;

    protected static bool $isLazy = true;

    public static function canView(): bool
    {
        // Hanya untuk platform super_admin — bukan untuk staff pesantren
        return auth()->user()?->isPlatformSuperAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $billing = app(BillingService::class);
        $stats = $billing->getStats();

        return [
            Stat::make('MRR', 'Rp '.number_format($stats['mrr'], 0, ',', '.'))
                ->description('Monthly Recurring Revenue')
                ->color('success')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Tenant Aktif', $stats['active_tenants'])
                ->description('Berlangganan aktif')
                ->color('info')
                ->icon('heroicon-o-building-library'),

            Stat::make('Invoice Belum Bayar', $stats['unpaid_invoices'])
                ->description('Perlu tindak lanjut')
                ->color($stats['unpaid_invoices'] > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-document-currency-dollar'),

            Stat::make('Total Pendapatan', 'Rp '.number_format($stats['total_revenue'], 0, ',', '.'))
                ->description('Kumulatif invoice lunas')
                ->color('warning')
                ->icon('heroicon-o-chart-bar'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}
