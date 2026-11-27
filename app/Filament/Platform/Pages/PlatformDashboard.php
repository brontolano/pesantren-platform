<?php

namespace App\Filament\Platform\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Dashboard;

class PlatformDashboard extends Dashboard
{
    protected static ?string $title = 'Ruang kendali';

    public function getHeading(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        // The dashboard provides its own contextual heading and action row.
        return null;
    }

    public function getColumns(): int | array
    {
        return 1;
    }

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin'
            && (auth()->user()?->isPlatformSuperAdmin() ?? false);
    }
}
