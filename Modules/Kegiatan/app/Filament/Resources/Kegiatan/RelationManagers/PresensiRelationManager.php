<?php

namespace Modules\Kegiatan\Filament\Resources\Kegiatan\RelationManagers;

use App\Filament\Forms\SantriSelect;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * WP-26 — Presensi kegiatan dari panel admin.
 *
 * Sebelumnya kehadiran HANYA bisa diisi lewat App Santri (Pengurus); staf di panel
 * tak bisa catat/koreksi. RelationManager ini membungkus tabel presensi_kegiatan
 * langsung (kegiatan->presensi). Kosakata status KANONIK = hadir|izin|sakit|alpha
 * (samakan dgn AppSantriPengurusController::simpanPresensi + hitungan MutuService).
 *
 * Unik (tenant_id, kegiatan_id, santri_id): satu baris per santri per kegiatan —
 * tambah-ulang santri yang sama akan ditolak DB (gunakan Edit untuk ubah).
 */
class PresensiRelationManager extends RelationManager
{
    protected static string $relationship = 'presensi';

    protected static ?string $title = 'Kehadiran Santri';

    /** @var array<string, string> */
    private const STATUS = ['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpha' => 'Alpa'];

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            SantriSelect::make('santri_id')
                ->required(),
            Select::make('status')
                ->label('Status')
                ->options(self::STATUS)
                ->required()
                ->default('hadir'),
            Textarea::make('keterangan')
                ->label('Keterangan')
                ->rows(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->searchable()->sortable(),
                TextColumn::make('santri.nis')->label('NIS')->toggleable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'alpha' => 'danger',
                        'sakit' => 'warning',
                        'izin' => 'info',
                        default => 'success',
                    }),
                TextColumn::make('keterangan')->label('Keterangan')->limit(40)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUS),
            ])
            ->headerActions([
                CreateAction::make()->label('Catat Kehadiran'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('santri_id');
    }
}
