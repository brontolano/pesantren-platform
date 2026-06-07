<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Dashboard panel tenant dengan grid 6 kolom (responsif) agar columnSpan widget
 * sesuai mockup: banner full · area 4 + donat 2 · tabel 4 + agenda 2 · quick 2.
 * Hanya panel tenant (central tetap pakai Dashboard bawaan 2 kolom).
 */
class TenantDashboard extends BaseDashboard
{
    protected static ?string $title = 'Dasbor';

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 6,
        ];
    }
}
