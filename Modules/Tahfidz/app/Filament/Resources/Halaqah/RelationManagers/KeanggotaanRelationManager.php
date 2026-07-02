<?php

namespace Modules\Tahfidz\Filament\Resources\Halaqah\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Tahfidz\Models\HalaqahSantri;

/**
 * Kelola anggota Halaqah dari panel (gap 89) — tambah/keluarkan tanpa Import Excel.
 *
 * Pakai relasi HasMany `keanggotaan` (model HalaqahSantri ber-BelongsToTenant →
 * tenant_id ter-set otomatis saat create), BUKAN BelongsToMany `santri` — attach
 * pivot melewati event model sehingga tenant_id NULL (langgar NOT NULL).
 * Keluar = isi Tanggal Keluar (soft-close, riwayat tersimpan), BUKAN hapus baris.
 */
class KeanggotaanRelationManager extends RelationManager
{
    protected static string $relationship = 'keanggotaan';

    protected static ?string $title = 'Anggota Halaqah';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('santri_id')
                ->label('Santri')
                ->relationship('santri', 'nama')
                ->searchable()
                ->preload()
                ->required(),
            DatePicker::make('joined_at')
                ->label('Tanggal Masuk')
                ->default(now())
                ->required(),
            DatePicker::make('left_at')
                ->label('Tanggal Keluar')
                ->helperText('Isi untuk mengeluarkan anggota — riwayat tetap tersimpan (jangan hapus baris).'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->searchable()->sortable(),
                TextColumn::make('santri.nis')->label('NIS')->toggleable(),
                TextColumn::make('joined_at')->label('Masuk')->date('d M Y')->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (HalaqahSantri $record): string => $record->left_at ? 'Keluar' : 'Aktif')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Aktif' ? 'success' : 'gray'),
                TextColumn::make('left_at')->label('Keluar')->date('d M Y')->placeholder('—')->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah Anggota'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('joined_at', 'desc');
    }
}
