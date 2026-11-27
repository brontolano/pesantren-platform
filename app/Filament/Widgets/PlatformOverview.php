<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AktivasiModulTenant;
use App\Filament\Pages\SupportDesk;
use App\Filament\Resources\PlatformAnnouncements\PlatformAnnouncementResource;
use App\Filament\Resources\DevProjects\DevProjectResource;
use App\Filament\Resources\DevTasks\DevTaskResource;
use App\Filament\Resources\Tenants\TenantResource;
use App\Services\PlatformOperationsSnapshot;
use Filament\Widgets\Widget;

class PlatformOverview extends Widget
{
    protected string $view = 'filament.widgets.platform-overview';

    // Keep the central dashboard useful when Livewire's lazy request is delayed
    // or unavailable; this snapshot makes only a small set of central queries.
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -20;

    public static function canView(): bool
    {
        return auth()->user()?->isPlatformSuperAdmin() ?? false;
    }

    protected function getViewData(): array
    {
        $snapshot = app(PlatformOperationsSnapshot::class)->capture();

        return [
            'snapshot' => $snapshot,
            'tenantsUrl' => TenantResource::getUrl('index'),
            'createTenantUrl' => TenantResource::getUrl('create'),
            'modulesUrl' => AktivasiModulTenant::getUrl(),
            'announcementsUrl' => PlatformAnnouncementResource::getUrl('index'),
            'supportUrl' => SupportDesk::getUrl(),
            'projectsUrl' => DevProjectResource::getUrl('index'),
            'tasksUrl' => DevTaskResource::getUrl('index'),
        ];
    }
}
