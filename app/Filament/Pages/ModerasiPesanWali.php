<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Modules\Kesantrian\Models\PesanWali;
use Modules\Kesantrian\Services\PesanWaliService;

/**
 * F3-PWA — Antrean moderasi pesan 'perhatian' guru → wali.
 *
 * Setujui → status terkirim + delivery (notif Portal Wali + WA wali);
 * Tolak → status ditolak + alasan (guru melihatnya di riwayat PWA).
 * Terdaftar EKSPLISIT di TenantPanelProvider (selain auto-discover).
 */
class ModerasiPesanWali extends Page implements HasTable
{
    use InteractsWithTable;
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static string|\UnitEnum|null $navigationGroup = 'Kepengasuhan';

    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.pages.moderasi-pesan-wali';

    public static function getNavigationLabel(): string
    {
        return 'Moderasi Pesan Wali';
    }

    public function getTitle(): string
    {
        return 'Moderasi Pesan Wali';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = PesanWali::query()->where('status', 'menunggu_moderasi')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canAccess(): bool
    {
        // Fitur moderasi pesan ke Portal Wali → digerbang modul portal_wali
        // (selain izin manage_santri). shouldRegisterNavigation() me-return ini.
        return static::moduleVisible('portal_wali') && (auth()->user()?->can('manage_santri') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getSubheading(): ?string
    {
        return "Pesan jenis 'perhatian' dari guru tertahan di sini saat saklar moderasi aktif. Setujui = terkirim ke wali (portal + WA); Tolak wajib beralasan.";
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PesanWali::query()->with(['guru', 'santri', 'moderator']),
            )
            ->columns([
                TextColumn::make('created_at')->label('Diajukan')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('guru.name')->label('Guru')->searchable(),
                TextColumn::make('santri.nama')->label('Santri')->searchable(),
                TextColumn::make('jenis')->label('Jenis')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'apresiasi' => 'success',
                        'perhatian' => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('isi')->label('Isi Pesan')->limit(80)->wrap(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'terkirim' => 'success',
                        'menunggu_moderasi' => 'warning',
                        'ditolak' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('alasan_tolak')->label('Alasan Tolak')->limit(50)->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(array_combine(PesanWali::STATUS, PesanWali::STATUS))
                    ->default('menunggu_moderasi'),
            ])
            ->actions([
                Action::make('setujui')
                    ->label('Setujui')->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PesanWali $record): bool => $record->status === 'menunggu_moderasi')
                    ->action(function (PesanWali $record): void {
                        app(PesanWaliService::class)->setujui($record, (int) Auth::id());
                        Notification::make()->title('Pesan disetujui & terkirim ke wali')->success()->send();
                    }),
                Action::make('tolak')
                    ->label('Tolak')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (PesanWali $record): bool => $record->status === 'menunggu_moderasi')
                    ->schema([
                        Textarea::make('alasan')->label('Alasan penolakan')->required()->rows(3)->maxLength(1000),
                    ])
                    ->action(function (array $data, PesanWali $record): void {
                        app(PesanWaliService::class)->tolak($record, (int) Auth::id(), $data['alasan']);
                        Notification::make()->title('Pesan ditolak')->warning()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50]);
    }
}
