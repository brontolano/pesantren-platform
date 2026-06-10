<?php

namespace App\Filament\Resources\Surat;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Resources\Surat\Pages\ListSurat;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\Surat;

/**
 * Surat & Administrasi (Tema TU) — agenda surat masuk/keluar + arsip lampiran.
 * Nomor agenda/nomor surat keluar dihasilkan SuratService (race-safe) → baris
 * bersifat arsip: dibuat lewat aksi "Catat Surat", tidak diedit nomornya.
 */
class SuratResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Surat::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?string $navigationLabel = 'Surat & Administrasi';

    protected static string|\UnitEnum|null $navigationGroup = 'Administrasi';

    protected static ?string $modelLabel = 'Surat';

    protected static ?string $pluralModelLabel = 'Surat & Administrasi';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user !== null && ($user->can('manage_tenant_settings') || $user->can('manage_santri'));
    }

    public static function canCreate(): bool
    {
        return false; // dibuat via aksi "Catat Surat" (penomoran lewat service).
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jenis')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'masuk' ? 'info' : 'success'),
                TextColumn::make('nomor_urut')->label('Agenda')->sortable(),
                TextColumn::make('nomor_surat')->label('Nomor Surat')->searchable(),
                TextColumn::make('tanggal_surat')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('pihak')->label('Dari / Kepada')->searchable()->limit(30),
                TextColumn::make('perihal')->label('Perihal')->searchable()->limit(40),
                TextColumn::make('lampiran_path')
                    ->label('Lampiran')
                    ->formatStateUsing(fn (?string $state): string => $state ? 'Ada' : '—')
                    ->url(fn (Surat $record): ?string => $record->lampiran_path
                        ? '/_tenant-storage/'.ltrim($record->lampiran_path, '/')
                        : null, shouldOpenInNewTab: true),
            ])
            ->filters([
                SelectFilter::make('jenis')->options(['masuk' => 'Masuk', 'keluar' => 'Keluar']),
                SelectFilter::make('tahun')->options(
                    fn () => Surat::query()->distinct()->orderByDesc('tahun')
                        ->pluck('tahun', 'tahun')->all()
                ),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSurat::route('/'),
        ];
    }
}
