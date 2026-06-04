<?php

namespace App\Filament\Resources\Kegiatan;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\Kegiatan\Kegiatan\Pages\CreateKegiatan;
use App\Filament\Resources\Kegiatan\Kegiatan\Pages\EditKegiatan;
use App\Filament\Resources\Kegiatan\Kegiatan\Pages\ListKegiatan;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Kegiatan\Models\Kegiatan;

class KegiatanResource extends Resource
{
    protected static ?string $model = Kegiatan::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Kegiatan';

    protected static UnitEnum|string|null $navigationGroup = 'Kegiatan & Perizinan';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('nama')->label('Nama Kegiatan')->required()->maxLength(255),
            Select::make('jenis')
                ->label('Jenis')
                ->options(['umum' => 'Umum', 'wajib' => 'Wajib', 'sunnah' => 'Sunnah'])
                ->required()->default('umum'),
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
                        'selesai'     => 'success',
                        'berlangsung' => 'warning',
                        'dibatalkan'  => 'danger',
                        default       => 'gray',
                    }),
            ])
            ->filters([SelectFilter::make('status')->options(['terjadwal' => 'Terjadwal', 'berlangsung' => 'Berlangsung', 'selesai' => 'Selesai'])])
            ->actions([EditAction::make()])
            ->defaultSort('mulai', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListKegiatan::route('/'),
            'create' => CreateKegiatan::route('/create'),
            'edit'   => EditKegiatan::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_santri') ?? false;
    }
}
