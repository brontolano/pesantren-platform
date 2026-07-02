<?php

namespace Modules\Pembiayaan\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Pembiayaan\Filament\Resources\Pages\ListMutasiAset;
use Modules\Pembiayaan\Models\MutasiAset;

/**
 * G2-13 — Riwayat mutasi lokasi & pelepasan aset (read-only).
 * Pelepasan & mutasi dibuat dari aksi pada Aset; di sini hanya histori.
 */
class MutasiAsetResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = MutasiAset::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Mutasi / Pelepasan Aset';

    protected static ?string $modelLabel = 'Mutasi Aset';

    protected static ?string $pluralModelLabel = 'Mutasi / Pelepasan Aset';

    protected static string|\UnitEnum|null $navigationGroup = 'Aset & Sarpras';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('pembiayaan') && (auth()->user()?->can('manage_pembiayaan') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')->label('Tanggal')->date(),
                TextColumn::make('aset.nama_aset')->label('Aset')->searchable(),
                TextColumn::make('tipe')->label('Tipe')->badge()
                    ->formatStateUsing(fn (?string $s): string => $s === 'disposal' ? 'Pelepasan' : 'Mutasi Lokasi')
                    ->color(fn (?string $s): string => $s === 'disposal' ? 'danger' : 'info'),
                TextColumn::make('ke_lokasi')->label('Ke Lokasi')->placeholder('—')->toggleable(),
                TextColumn::make('harga_jual')->label('Harga Jual')->money('IDR')->toggleable(),
                TextColumn::make('nilai_buku')->label('Nilai Buku')->money('IDR')->toggleable(),
                TextColumn::make('laba_rugi')->label('Laba/Rugi')->money('IDR')
                    ->color(fn ($state): string => (float) $state < 0 ? 'danger' : 'success'),
                IconColumn::make('tanpa_pl')->label('Wakaf (tanpa P/L)')->boolean()->toggleable(),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                SelectFilter::make('tipe')->options(['mutasi_lokasi' => 'Mutasi Lokasi', 'disposal' => 'Pelepasan']),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMutasiAset::route('/'),
        ];
    }
}
