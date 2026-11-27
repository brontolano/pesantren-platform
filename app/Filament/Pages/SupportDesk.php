<?php

namespace App\Filament\Pages;

use App\Models\PlatformSupportReply;
use App\Models\PlatformSupportTicket;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SupportDesk extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Kasus Dukungan';

    protected static ?string $title = 'Dukungan Pesantren.online';

    protected static string|\UnitEnum|null $navigationGroup = 'Komunikasi & Dukungan';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.support-desk';

    public ?int $selectedTicketId = null;

    public string $replyBody = '';

    public bool $internalNote = false;

    public string $status = 'open';

    public string $priority = 'normal';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin'
            && (auth()->user()?->isPlatformSuperAdmin() ?? false);
    }

    public function getViewData(): array
    {
        $tickets = PlatformSupportTicket::query()->with('tenant')->withCount('replies')->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'waiting' THEN 1 ELSE 2 END")
            ->orderByDesc('last_reply_at')->orderByDesc('created_at')->limit(100)->get();
        $selected = $this->selectedTicketId
            ? PlatformSupportTicket::query()->with(['tenant', 'replies' => fn ($query) => $query->orderBy('created_at')])->find($this->selectedTicketId)
            : $tickets->first();

        if ($selected && $this->selectedTicketId === null) {
            $this->selectedTicketId = (int) $selected->getKey();
            $this->status = $selected->status;
            $this->priority = $selected->priority;
        }

        return ['tickets' => $tickets, 'selected' => $selected];
    }

    public function selectTicket(int $ticketId): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(PlatformSupportTicket::query()->whereKey($ticketId)->exists(), 404);
        $this->selectedTicketId = $ticketId;
        $this->status = (string) PlatformSupportTicket::query()->whereKey($ticketId)->value('status');
        $this->priority = (string) PlatformSupportTicket::query()->whereKey($ticketId)->value('priority');
        $this->replyBody = '';
    }

    public function reply(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->validate(['replyBody' => ['required', 'string', 'min:2', 'max:10000'], 'internalNote' => ['boolean']]);
        $ticket = PlatformSupportTicket::query()->findOrFail($this->selectedTicketId);
        $user = auth()->user();

        PlatformSupportReply::query()->create([
            'platform_support_ticket_id' => $ticket->getKey(),
            'author_user_id' => $user->getKey(),
            'author_name' => $user->name,
            'is_internal' => (bool) $data['internalNote'],
            'body' => trim($data['replyBody']),
        ]);
        $ticketChanges = ['last_reply_at' => now()];
        if (! $data['internalNote']) {
            $ticketChanges['status'] = 'waiting';
            $ticketChanges['status_updated_by'] = $user->getKey();
            $ticketChanges['status_updated_at'] = now();
        }
        $ticket->update($ticketChanges);
        $this->replyBody = '';
        Notification::make()->title($data['internalNote'] ? 'Catatan internal tersimpan' : 'Balasan dikirim; menunggu jawaban tenant')->success()->send();
    }

    public function updateStatus(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->validate([
            'status' => ['required', 'in:open,waiting,closed'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
        ]);
        $ticket = PlatformSupportTicket::query()->findOrFail($this->selectedTicketId);
        $ticket->update(['status' => $data['status'], 'priority' => $data['priority'], 'status_updated_by' => auth()->id(), 'status_updated_at' => now()]);
        Notification::make()->title('Status kasus diperbarui')->success()->send();
    }
}
