<?php

namespace App\Filament\Resources\Tahfidz;

use Filament\Forms\Components\Card;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use BackedEnum;
use UnitEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use App\Filament\Resources\Tahfidz\PenilaianTahfidzResource\Pages\CreatePenilaianTahfidz;
use App\Filament\Resources\Tahfidz\PenilaianTahfidzResource\Pages\EditPenilaianTahfidz;
use App\Filament\Resources\Tahfidz\PenilaianTahfidzResource\Pages\ListPenilaianTahfidz;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Tahfidz\Models\PenilaianTahfidz;
use Modules\Tahfidz\Models\HafalanTahfidz;
use Modules\Kesantrian\Models\Santri;

class PenilaianTahfidzResource extends Resource
{
    protected static ?string $model = PenilaianTahfidz::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationLabel = 'Penilaian Tahfidz';

    protected static UnitEnum|string|null $navigationGroup = 'Tahfidz';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Card::make()->schema([
                    Select::make('santri_id')
                        ->label('Santri')
                        ->options(Santri::query()->pluck('nama', 'id'))
                        ->required(),
                    Select::make('hafalan_id')
                        ->label('Hafalan')
                        ->options(HafalanTahfidz::query()->pluck('surah', 'id'))
                        ->required(),
                    Select::make('jenis')
                        ->label('Jenis')
                        ->options([
                            'murajaah' => 'Murajaah',
                            'ziyadah' => 'Ziyadah',
                            'ujian' => 'Ujian',
                        ])
                        ->required(),
                    TextInput::make('nilai')
                        ->label('Nilai')
                        ->numeric()
                        ->nullable(),
                    DatePicker::make('tanggal')
                        ->label('Tanggal')
                        ->required(),
                    Textarea::make('catatan')
                        ->label('Catatan')
                        ->rows(3),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->sortable()->searchable(),
                TextColumn::make('hafalan.surah')->label('Hafalan'),
                TextColumn::make('jenis')->label('Jenis')->sortable(),
                TextColumn::make('nilai')->label('Nilai'),
                TextColumn::make('tanggal')->label('Tanggal')->date(),
                TextColumn::make('updated_at')->label('Diubah')->since(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPenilaianTahfidz::route('/'),
            'create' => CreatePenilaianTahfidz::route('/create'),
            'edit' => EditPenilaianTahfidz::route('/{record}/edit'),
        ];
    }
}

