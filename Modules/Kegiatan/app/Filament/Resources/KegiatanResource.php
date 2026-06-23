<?php

namespace Modules\Kegiatan\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use Modules\Kegiatan\Filament\Resources\Kegiatan\Pages\CreateKegiatan;
use Modules\Kegiatan\Filament\Resources\Kegiatan\Pages\EditKegiatan;
use Modules\Kegiatan\Filament\Resources\Kegiatan\Pages\ListKegiatan;
use Modules\Kegiatan\Filament\Resources\Kegiatan\RelationManagers\PresensiRelationManager;
use App\Support\UiMode;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Kegiatan\Models\Kegiatan;
use UnitEnum;

class KegiatanResource extends Resource
{
    /** Mode simple: menu lanjutan disembunyikan (fungsi & route tetap aktif). */
    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced();
    }

    use ModuleGate;

    protected static ?string $model = Kegiatan::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Kegiatan';

    protected static UnitEnum|string|null $navigationGroup = 'Kepengasuhan';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('nama')->label('Nama Kegiatan')->required()->maxLength(255),
            Select::make('jenis')
                ->label('Jenis')
                // WP-11: 'pasaran' (pasaran/kilatan) — string bebas, tanpa enum DB.
                ->options(['umum' => 'Umum', 'wajib' => 'Wajib', 'sunnah' => 'Sunnah', 'pasaran' => 'Pasaran / Kilatan'])
                ->required()->default('umum')
                ->live(),
            DateTimePicker::make('mulai')->label('Mulai')->required(),
            DateTimePicker::make('selesai')->label('Selesai'),
            TextInput::make('lokasi')->label('Lokasi')->maxLength(255),
            Select::make('status')
                ->label('Status')
                ->options(['terjadwal' => 'Terjadwal', 'berlangsung' => 'Berlangsung', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'])
                ->required()->default('terjadwal'),
            Select::make('penanggung_jawab_id')
                ->label('Penanggung Jawab')
                ->relationship('penanggungJawab', 'name')
                ->searchable()->preload()->nullable(),
            Textarea::make('deskripsi')->label('Deskripsi')->rows(3),
            // WP-11 — peserta luar pasaran/kilatan: tersimpan di JSON kolom
            // kegiatan.peserta_luar, TIDAK membuat baris santri/presensi_kegiatan.
            Repeater::make('peserta_luar')
                ->label('Peserta Luar (santri kalong / tamu)')
                ->visible(fn (Get $get): bool => $get('jenis') === 'pasaran')
                ->schema([
                    TextInput::make('nama')->label('Nama')->required()->maxLength(255),
                    TextInput::make('asal')->label('Asal')->maxLength(255),
                    Toggle::make('hadir')->label('Hadir')->default(true),
                ])
                ->default([])
                ->maxItems(500)
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('jenis')->label('Jenis')->badge(),
                TextColumn::make('mulai')->label('Mulai')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('lokasi')->label('Lokasi')->limit(30),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'selesai' => 'success',
                        'berlangsung' => 'warning',
                        'dibatalkan' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([SelectFilter::make('status')->options(['terjadwal' => 'Terjadwal', 'berlangsung' => 'Berlangsung', 'selesai' => 'Selesai'])])
            ->actions([EditAction::make()])
            ->defaultSort('mulai', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            PresensiRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKegiatan::route('/'),
            'create' => CreateKegiatan::route('/create'),
            'edit' => EditKegiatan::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('kegiatan') && (auth()->user()?->can('view_santri') ?? false);
    }
}
