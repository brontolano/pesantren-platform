<?php

namespace App\Filament\Resources\BaitulMaal;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Resources\BaitulMaal\Pages\ListDonasi;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Keuangan\Models\Donasi;
use Modules\Keuangan\Services\DonasiService;
use Throwable;

/**
 * 2c — Donasi masuk (niat publik). Bendahara melihat bukti transfer lalu
 * Konfirmasi (posting jurnal idempoten via DonasiService) atau Tolak.
 * Record dibuat dari halaman publik /donasi — tidak ada create/edit di panel.
 */
class DonasiResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Donasi::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Donasi Masuk';

    protected static ?string $modelLabel = 'Donasi';

    protected static ?string $pluralModelLabel = 'Donasi';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 31;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('keuangan') && (auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return false; // donasi hanya lahir dari halaman publik /donasi.
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Tanggal')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('nama_donatur')->label('Donatur')->searchable(),
                TextColumn::make('kontak')->label('Kontak WA')->placeholder('—')->toggleable(),
                TextColumn::make('program.nama')->label('Program'),
                TextColumn::make('nominal')->label('Nominal')->money('IDR')->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Donasi::STATUS_DIKONFIRMASI => 'success',
                        Donasi::STATUS_DITOLAK => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('nomor_kuitansi')->label('Kuitansi')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    Donasi::STATUS_MENUNGGU => 'Menunggu Transfer',
                    Donasi::STATUS_DIKONFIRMASI => 'Dikonfirmasi',
                    Donasi::STATUS_DITOLAK => 'Ditolak',
                ]),
            ])
            ->actions([
                Action::make('lihat_bukti')
                    ->label('Bukti')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->url(fn (Donasi $record): string => url('/_tenant-storage/'.$record->bukti_path))
                    ->openUrlInNewTab()
                    ->visible(fn (Donasi $record): bool => filled($record->bukti_path)),
                Action::make('konfirmasi')
                    ->label('Konfirmasi')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi donasi & posting jurnal?')
                    ->modalDescription('D Kas / K Pendapatan sesuai sumber dana program. Idempoten — konfirmasi ulang tidak menggandakan jurnal.')
                    ->visible(fn (Donasi $record): bool => $record->status === Donasi::STATUS_MENUNGGU
                        && (auth()->user()?->can('manage_keuangan') ?? false))
                    ->action(function (Donasi $record): void {
                        try {
                            app(DonasiService::class)->konfirmasi($record, auth()->id());
                            Notification::make()->success()->title('Donasi dikonfirmasi & jurnal terposting.')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Gagal konfirmasi')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Donasi $record): bool => $record->status === Donasi::STATUS_MENUNGGU
                        && (auth()->user()?->can('manage_keuangan') ?? false))
                    ->action(function (Donasi $record): void {
                        app(DonasiService::class)->tolak($record, auth()->id());
                        Notification::make()->success()->title('Donasi ditolak (tidak ada jurnal).')->send();
                    }),
                Action::make('kuitansi')
                    ->label('Kuitansi')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Donasi $record): string => route('tenant.donasi.kuitansi', ['donasi' => $record->id]))
                    ->openUrlInNewTab()
                    ->visible(fn (Donasi $record): bool => $record->status === Donasi::STATUS_DIKONFIRMASI),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDonasi::route('/'),
        ];
    }
}
