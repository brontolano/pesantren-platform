<?php

namespace App\Filament\Resources\Sarpras;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Resources\Sarpras\LaporanKerusakan\Pages\EditLaporanKerusakan;
use App\Filament\Resources\Sarpras\LaporanKerusakan\Pages\ListLaporanKerusakan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Modules\Pembiayaan\Models\LaporanKerusakan;
use Modules\Pembiayaan\Services\SarprasService;
use Throwable;
use UnitEnum;

/**
 * WP-33 — Laporan Kerusakan sarpras (masuk via PWA Guru).
 * Panel HANYA mengubah status (searah) + mencatat biaya sebagai INFO.
 * Bila ada biaya → hint "buat pengeluaran via modul Keuangan" — SENGAJA
 * tidak auto-posting ke ledger (posting uang wajib lewat modul Keuangan).
 */
class LaporanKerusakanResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = LaporanKerusakan::class;

    protected static ?string $slug = 'sarpras/laporan-kerusakan';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Laporan Kerusakan';

    protected static ?string $modelLabel = 'Laporan Kerusakan';

    protected static UnitEnum|string|null $navigationGroup = 'Sarana & Logistik';

    protected static ?int $navigationSort = 1;

    /** Badge jumlah laporan baru (status masuk). */
    public static function getNavigationBadge(): ?string
    {
        $masuk = LaporanKerusakan::query()->where('status', 'masuk')->count();

        return $masuk > 0 ? (string) $masuk : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('lokasi')->label('Lokasi')->disabled(),
            Textarea::make('deskripsi')->label('Deskripsi')->rows(3)->disabled(),
            Placeholder::make('foto')
                ->label('Foto')
                ->content(fn (?LaporanKerusakan $record): HtmlString => new HtmlString(
                    $record?->fotoUrl()
                        ? '<a href="'.e($record->fotoUrl()).'" target="_blank" class="text-primary-600 underline">Lihat foto laporan</a>'
                        : '—'
                )),
            TextInput::make('biaya')
                ->label('Biaya Perbaikan (info)')
                ->numeric()
                ->minValue(0)
                ->helperText('Bila ada biaya: buat pengeluaran via modul Keuangan (Belanja/Jurnal) — laporan ini TIDAK memposting otomatis.'),
            Textarea::make('catatan')->label('Catatan Penanganan')->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Tanggal')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('lokasi')->label('Lokasi')->searchable(),
                TextColumn::make('deskripsi')->label('Deskripsi')->limit(40),
                TextColumn::make('pelapor.name')->label('Pelapor'),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'masuk' => 'warning',
                        'dikerjakan' => 'info',
                        default => 'success',
                    }),
                TextColumn::make('biaya')->label('Biaya')->money('IDR')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['masuk' => 'Masuk', 'dikerjakan' => 'Dikerjakan', 'selesai' => 'Selesai']),
            ])
            ->actions([
                Action::make('kerjakan')
                    ->label('Kerjakan')
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->visible(fn (LaporanKerusakan $record): bool => $record->status === 'masuk')
                    ->action(fn (LaporanKerusakan $record) => static::ubahStatus($record, 'dikerjakan')),
                Action::make('selesai')
                    ->label('Selesai')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (LaporanKerusakan $record): bool => $record->status === 'dikerjakan')
                    ->form([
                        TextInput::make('biaya')->label('Biaya (opsional, info saja)')->numeric()->minValue(0),
                        TextInput::make('catatan')->label('Catatan')->maxLength(500),
                    ])
                    ->action(function (LaporanKerusakan $record, array $data): void {
                        static::ubahStatus($record, 'selesai', [
                            'biaya' => $data['biaya'] !== null && $data['biaya'] !== '' ? $data['biaya'] : null,
                            'catatan' => $data['catatan'] ?? null,
                        ]);
                    }),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected static function ubahStatus(LaporanKerusakan $record, string $status, array $opts = []): void
    {
        try {
            $laporan = app(SarprasService::class)->ubahStatus($record, $status, $opts);
            $body = $laporan->biaya !== null
                ? 'Ada biaya tercatat — jangan lupa buat pengeluaran via modul Keuangan.'
                : null;
            Notification::make()->success()->title("Status → {$status}")->body($body)->send();
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLaporanKerusakan::route('/'),
            'edit' => EditLaporanKerusakan::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Laporan masuk dari PWA Guru, bukan dibuat di panel.
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('pembiayaan') && static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('pembiayaan') && auth()->check();
    }
}
