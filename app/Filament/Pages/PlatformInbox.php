<?php

namespace App\Filament\Pages;

use App\Models\PlatformAnnouncement;
use App\Models\PlatformAnnouncementRead;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PlatformInbox extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox';

    protected static ?string $navigationLabel = 'Pesan Platform';

    protected static ?string $title = 'Informasi dari Pesantren.online';

    protected static string|\UnitEnum|null $navigationGroup = 'Komunikasi';

    protected static ?int $navigationSort = -5;

    protected string $view = 'filament.pages.platform-inbox';

    public string $folder = 'inbox';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && tenant() !== null
            && (auth()->user()?->isTenantStaff() ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }

        $tenantId = (string) tenant()->getTenantKey();
        $unread = PlatformAnnouncement::query()->visibleTo($tenantId)
            ->whereDoesntHave('reads', fn ($query) => $query->where('tenant_id', $tenantId)->where('user_id', auth()->id())->whereNull('archived_at'))
            ->whereDoesntHave('reads', fn ($query) => $query->where('tenant_id', $tenantId)->where('user_id', auth()->id())->whereNotNull('archived_at'))
            ->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public function getViewData(): array
    {
        $tenantId = (string) tenant()->getTenantKey();
        $userId = auth()->id();
        $announcementsQuery = PlatformAnnouncement::query()->visibleTo($tenantId)
            ->with(['reads' => fn ($query) => $query->where('tenant_id', $tenantId)->where('user_id', $userId)])
            ->orderByRaw("CASE priority WHEN 'mendesak' THEN 0 WHEN 'penting' THEN 1 ELSE 2 END")
            ->orderByDesc('published_at')
            ->limit(100);

        if ($this->folder === 'archive') {
            $announcementsQuery->whereHas('reads', fn ($query) => $query->where('tenant_id', $tenantId)->where('user_id', $userId)->whereNotNull('archived_at'));
        } else {
            $announcementsQuery->whereDoesntHave('reads', fn ($query) => $query->where('tenant_id', $tenantId)->where('user_id', $userId)->whereNotNull('archived_at'));
        }

        return ['announcements' => $announcementsQuery->get()];
    }

    public function markAsRead(int $announcementId): void
    {
        abort_unless(static::canAccess(), 403);

        $tenantId = (string) tenant()->getTenantKey();
        $announcement = PlatformAnnouncement::query()->visibleTo($tenantId)->findOrFail($announcementId);

        PlatformAnnouncementRead::query()->updateOrCreate(
            [
                'platform_announcement_id' => $announcement->getKey(),
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
            ],
            ['read_at' => now()]
        );

        Notification::make()->title('Pesan ditandai sudah dibaca')->success()->send();
    }

    public function toggleArchive(int $announcementId): void
    {
        abort_unless(static::canAccess(), 403);

        $tenantId = (string) tenant()->getTenantKey();
        $announcement = PlatformAnnouncement::query()->visibleTo($tenantId)->findOrFail($announcementId);
        $read = PlatformAnnouncementRead::query()->firstOrNew([
            'platform_announcement_id' => $announcement->getKey(),
            'tenant_id' => $tenantId,
            'user_id' => auth()->id(),
        ]);
        $read->read_at ??= now();
        $read->archived_at = $this->folder === 'archive' ? null : now();
        $read->save();

        Notification::make()->title($this->folder === 'archive' ? 'Pesan dikembalikan ke kotak masuk' : 'Pesan dipindahkan ke arsip')->success()->send();
    }
}
