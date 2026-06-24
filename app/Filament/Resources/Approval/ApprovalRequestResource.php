<?php

namespace App\Filament\Resources\Approval;

use App\Filament\Resources\Approval\ApprovalRequest\Pages\ListApprovalRequest;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\ApprovalRule;
use Modules\Core\Services\ApprovalService;

/**
 * Inbox "Persetujuan Saya" (F1) — daftar permintaan persetujuan yang MENUNGGU
 * dan peran-nya dimiliki user login. List-only + aksi Setujui/Tolak.
 */
class ApprovalRequestResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationLabel = 'Persetujuan Saya';

    protected static ?string $modelLabel = 'Permintaan Persetujuan';

    protected static ?string $pluralModelLabel = 'Persetujuan Saya';

    protected static string|\UnitEnum|null $navigationGroup = 'Tata Kelola & Audit';

    protected static ?int $navigationSort = 29;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant' && Auth::check();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /** Hanya request menunggu untuk peran milik user login. */
    public static function getEloquentQuery(): Builder
    {
        $roles = Auth::user()?->getRoleNames() ?? collect();

        return parent::getEloquentQuery()
            ->where('status', 'menunggu')
            ->whereIn('approver_role', $roles->all() ?: ['__none__']);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::getEloquentQuery()->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('approvable_type')->label('Jenis')
                    ->formatStateUsing(fn (?string $state): string => ApprovalRule::APPROVABLE[$state] ?? class_basename((string) $state)),
                TextColumn::make('ringkasan')->label('Transaksi')
                    ->state(fn (ApprovalRequest $r): string => static::ringkas($r)),
                TextColumn::make('peminta.name')->label('Diajukan oleh')->placeholder('—'),
                TextColumn::make('created_at')->label('Diajukan')->dateTime('d M Y H:i'),
            ])
            ->defaultSort('created_at')
            ->actions([
                Action::make('setujui')
                    ->label('Setujui')->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->action(function (ApprovalRequest $record): void {
                        app(ApprovalService::class)->setujui($record, (int) Auth::id());
                        Notification::make()->title('Disetujui')->success()->send();
                    }),
                Action::make('tolak')
                    ->label('Tolak')->icon('heroicon-o-x-circle')->color('danger')
                    ->schema([
                        Textarea::make('alasan')->label('Alasan penolakan')->required()->rows(3)->maxLength(1000),
                    ])
                    ->action(function (array $data, ApprovalRequest $record): void {
                        app(ApprovalService::class)->tolak($record, (int) Auth::id(), $data['alasan']);
                        Notification::make()->title('Ditolak')->warning()->send();
                    }),
            ]);
    }

    private static function ringkas(ApprovalRequest $r): string
    {
        $m = $r->approvable;
        if ($m === null) {
            return '#'.$r->approvable_id;
        }
        $label = $m->uraian ?? $m->nama ?? $m->nomor ?? ('#'.$m->getKey());
        $nominal = $m->total ?? $m->nominal ?? null;

        return $nominal !== null
            ? $label.' — Rp '.number_format((float) $nominal, 0, ',', '.')
            : (string) $label;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalRequest::route('/'),
        ];
    }
}
