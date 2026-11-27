<?php

namespace App\Filament\Pages;

use App\Models\PlatformSupportReply;
use App\Models\PlatformSupportTicket;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class TenantSupport extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $navigationLabel = 'Minta Bantuan';

    protected static ?string $title = 'Bantuan Teknis';

    protected static string|\UnitEnum|null $navigationGroup = 'Komunikasi';

    protected static ?int $navigationSort = -4;

    protected string $view = 'filament.pages.tenant-support';

    public string $category = 'teknis';

    public string $impact = 'sebagian_terhambat';

    public string $subject = '';

    public string $description = '';

    public ?int $selectedTicketId = null;

    public string $replyBody = '';

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

        $count = PlatformSupportTicket::query()
            ->forTenant((string) tenant()->getTenantKey())
            ->where('status', '!=', 'closed')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public function getViewData(): array
    {
        $tickets = PlatformSupportTicket::query()->forTenant((string) tenant()->getTenantKey())
            ->with(['replies' => fn ($query) => $query->public()->orderBy('created_at')])
            ->orderByDesc('last_reply_at')->orderByDesc('created_at')->limit(50)->get();
        $selected = $this->selectedTicketId
            ? $tickets->firstWhere('id', $this->selectedTicketId)
            : $tickets->first();
        if ($selected) {
            $this->selectedTicketId = (int) $selected->getKey();
        }

        return ['tickets' => $tickets, 'selected' => $selected];
    }

    public function openTicket(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->validate([
            'category' => ['required', 'in:akun,website,modul,teknis,lainnya'],
            'impact' => ['required', 'in:tidak_bisa_kerja,sebagian_terhambat,pertanyaan'],
            'subject' => ['required', 'string', 'min:4', 'max:180'],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
        ]);
        $tenantId = (string) tenant()->getTenantKey();
        $user = auth()->user();
        $ticket = PlatformSupportTicket::query()->create([
            'ticket_number' => 'PO-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
            'tenant_id' => $tenantId,
            'created_by_user_id' => $user->getKey(),
            'category' => $data['category'],
            'impact' => $data['impact'],
            'priority' => 'normal',
            'subject' => trim($data['subject']),
            'description' => trim($data['description']),
            'status' => 'open',
            'status_updated_by' => $user->getKey(),
            'status_updated_at' => now(),
            'last_reply_at' => now(),
        ]);
        $this->selectedTicketId = (int) $ticket->getKey();
        $this->subject = '';
        $this->description = '';
        Notification::make()->title('Permintaan bantuan dibuat')->body('Nomor kasus: '.$ticket->ticket_number)->success()->send();
    }

    public function reply(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->validate(['replyBody' => ['required', 'string', 'min:2', 'max:10000']]);
        $ticket = PlatformSupportTicket::query()
            ->forTenant((string) tenant()->getTenantKey())
            ->whereKey($this->selectedTicketId)
            ->where('status', '!=', 'closed')
            ->firstOrFail();
        $user = auth()->user();
        PlatformSupportReply::query()->create([
            'platform_support_ticket_id' => $ticket->getKey(),
            'author_user_id' => $user->getKey(),
            'author_name' => $user->name,
            'is_internal' => false,
            'body' => trim($data['replyBody']),
        ]);
        $ticket->update(['last_reply_at' => now(), 'status' => 'open', 'status_updated_by' => $user->getKey(), 'status_updated_at' => now()]);
        $this->replyBody = '';
        Notification::make()->title('Balasan terkirim')->success()->send();
    }
}
