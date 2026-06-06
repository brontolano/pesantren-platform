<?php

namespace App\Filament\Resources\PPDB;

use App\Filament\Resources\PPDB\TagihanPpdb\Pages\ListTagihanPpdb;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\PPDB\Models\TagihanPpdb;

/**
 * Verifikasi tagihan PPDB (panitia). Read + aksi: Konfirmasi Lunas / Tolak Bukti.
 * Tagihan dibuat oleh PpdbBillingService; di sini hanya diverifikasi.
 */
class TagihanPpdbResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = TagihanPpdb::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Tagihan PPDB';

    protected static ?string $modelLabel = 'Tagihan PPDB';

    protected static ?string $pluralModelLabel = 'Tagihan PPDB';

    protected static string|\UnitEnum|null $navigationGroup = 'PPDB';

    protected static ?string $slug = 'ppdb/tagihan';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('ppdb') && static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('ppdb') && (Auth::user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        // Verifikasi dilakukan lewat aksi tabel, bukan form edit.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('calonSantri.nama')
                    ->label('Calon Santri')
                    ->searchable(),
                TextColumn::make('tipe')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('nominal')
                    ->label('Nominal')
                    ->money('IDR'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'lunas' => 'success',
                        'batal' => 'gray',
                        'menunggu_verifikasi' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('bukti_transfer')
                    ->label('Bukti')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? 'Ada' : '—')
                    ->color(fn (?string $state): string => $state ? 'info' : 'gray'),
                TextColumn::make('bukti_diunggah_at')
                    ->label('Diunggah')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Action::make('lihatBukti')
                    ->label('Lihat Bukti')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (TagihanPpdb $record): bool => $record->bukti_transfer !== null)
                    ->url(fn (TagihanPpdb $record): string => route('ppdb.file.bukti', $record), shouldOpenInNewTab: true),
                Action::make('konfirmasiLunas')
                    ->label('Konfirmasi Lunas')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (TagihanPpdb $record): bool => $record->status !== 'lunas' && (Auth::user()?->can('manage_santri') ?? false))
                    ->action(fn (TagihanPpdb $record) => $record->update(['status' => 'lunas'])),
                Action::make('tolakBukti')
                    ->label('Tolak Bukti')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (TagihanPpdb $record): bool => $record->bukti_transfer !== null && $record->status !== 'lunas' && (Auth::user()?->can('manage_santri') ?? false))
                    ->schema([
                        Textarea::make('keterangan')
                            ->label('Alasan / catatan (opsional)')
                            ->maxLength(500),
                    ])
                    ->action(fn (TagihanPpdb $record, array $data) => $record->update([
                        'status' => 'belum_bayar',
                        'bukti_transfer' => null,
                        'bukti_diunggah_at' => null,
                        'keterangan' => $data['keterangan'] ?? $record->keterangan,
                    ])),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('calonSantri');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTagihanPpdb::route('/'),
        ];
    }
}
