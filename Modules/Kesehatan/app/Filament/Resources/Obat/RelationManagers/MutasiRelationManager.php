<?php

namespace Modules\Kesehatan\Filament\Resources\Obat\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Riwayat mutasi stok obat (READ-ONLY) — jejak masuk/keluar yang sudah dicatat
 * ObatService lewat aksi Stok Masuk/Keluar (gap 102: data ada tapi tak ada UI).
 * Append-only: TIDAK boleh dibuat/diubah/dihapus dari sini (jaga integritas audit).
 */
class MutasiRelationManager extends RelationManager
{
    protected static string $relationship = 'mutasi';

    protected static ?string $title = 'Riwayat Mutasi Stok';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada mutasi')
            ->emptyStateDescription('Mutasi tercatat otomatis dari aksi Stok Masuk / Stok Keluar pada obat ini.')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('jenis')->label('Jenis')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'masuk' ? 'Masuk' : 'Keluar')
                    ->color(fn (string $state): string => $state === 'masuk' ? 'success' : 'warning'),
                TextColumn::make('jumlah')->label('Jumlah'),
                TextColumn::make('keterangan')->label('Keterangan')->limit(40)->placeholder('—'),
                TextColumn::make('user.name')->label('Oleh')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('jenis')->options(['masuk' => 'Masuk', 'keluar' => 'Keluar']),
            ])
            ->defaultSort('id', 'desc');
    }
}
