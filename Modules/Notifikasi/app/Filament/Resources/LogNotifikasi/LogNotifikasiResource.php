<?php

namespace Modules\Notifikasi\Filament\Resources\LogNotifikasi;

use App\Filament\Concerns\ModuleGate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Notifikasi\Filament\Resources\LogNotifikasi\Pages\ListLogNotifikasi;
use Modules\Notifikasi\Models\LogNotifikasi;
use UnitEnum;

/**
 * Log Notifikasi — riwayat kirim WA/email (terkirim/gagal/simulasi) agar staf
 * bisa memverifikasi pesan benar-benar sampai, bukan hanya berasumsi. READ-ONLY:
 * baris dibuat sistem (NotifDispatcher/BroadcastWaJob), tak boleh dibuat/diubah
 * dari panel.
 */
class LogNotifikasiResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = LogNotifikasi::class;

    protected static ?string $slug = 'komunikasi/log-notifikasi';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Log Notifikasi';

    protected static ?string $modelLabel = 'Log Notifikasi';

    protected static ?string $pluralModelLabel = 'Log Notifikasi';

    protected static UnitEnum|string|null $navigationGroup = 'Komunikasi';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('notifikasi') && (auth()->user()?->canAny(['view_notifikasi', 'manage_notifikasi']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('notifikasi') && (auth()->user()?->canAny(['view_notifikasi', 'manage_notifikasi']) ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('channel')->label('Kanal')->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'wa' => 'WhatsApp',
                        'email' => 'Email',
                        default => (string) $state,
                    }),
                TextColumn::make('target')->label('Tujuan')->searchable()->limit(30),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'sent', 'terkirim' => 'success',
                        'failed', 'gagal' => 'danger',
                        'simulated', 'disabled', 'skipped' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('error_message')->label('Keterangan / Error')->limit(50)->placeholder('—'),
                TextColumn::make('template.judul')->label('Template')->toggleable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('channel')->options(['wa' => 'WhatsApp', 'email' => 'Email']),
                SelectFilter::make('status')->options([
                    'sent' => 'Terkirim',
                    'failed' => 'Gagal',
                    'simulated' => 'Simulasi (WA belum dikonfigurasi)',
                    'disabled' => 'Dinonaktifkan',
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLogNotifikasi::route('/'),
        ];
    }
}
