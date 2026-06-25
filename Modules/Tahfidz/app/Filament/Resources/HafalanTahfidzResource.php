<?php

namespace Modules\Tahfidz\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Filament\Resources\HafalanTahfidzResource\Pages\CreateHafalanTahfidz;
use Modules\Tahfidz\Filament\Resources\HafalanTahfidzResource\Pages\EditHafalanTahfidz;
use Modules\Tahfidz\Filament\Resources\HafalanTahfidzResource\Pages\ListHafalanTahfidz;
use Modules\Tahfidz\Models\HafalanTahfidz;
use UnitEnum;

class HafalanTahfidzResource extends Resource
{
    use ModuleGate;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('tahfidz');
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('tahfidz');
    }

    protected static ?string $model = HafalanTahfidz::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Hafalan Tahfidz';

    protected static UnitEnum|string|null $navigationGroup = 'Tahfidz';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()->schema([
                    Select::make('santri_id')
                        ->label('Santri')
                        ->options(Santri::query()->pluck('nama', 'id'))
                        ->required(),
                    Select::make('halaqah_id')
                        ->label('Halaqah (opsional)')
                        ->relationship('halaqah', 'nama')
                        ->searchable()
                        ->preload()
                        ->helperText('Tautkan setoran ke halaqah/kelompok tahfidz. Boleh dikosongkan; tak perlu Import Excel.'),
                    TextInput::make('surah')
                        ->label('Surah')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('ayat_awal')
                        ->label('Ayat Awal')
                        ->numeric(),
                    TextInput::make('ayat_akhir')
                        ->label('Ayat Akhir')
                        ->numeric(),
                    DatePicker::make('tanggal_target')
                        ->label('Tanggal Target'),
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'direncanakan' => 'Direncanakan',
                            'berjalan' => 'Berjalan',
                            'selesai' => 'Selesai',
                        ])
                        ->default('direncanakan'),
                    Textarea::make('catatan')
                        ->label('Catatan')
                        ->rows(3),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada setoran hafalan')
            ->emptyStateDescription('Setoran hafalan santri akan tercatat di sini.')
            ->emptyStateIcon('heroicon-o-sparkles')
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->sortable()->searchable(),
                TextColumn::make('surah')->label('Surah')->sortable()->searchable(),
                TextColumn::make('ayat_awal')->label('Ayat Awal'),
                TextColumn::make('ayat_akhir')->label('Ayat Akhir'),
                TextColumn::make('tanggal_target')->label('Target')->date(),
                TextColumn::make('status')->label('Status')->sortable(),
                TextColumn::make('updated_at')->label('Diubah')->since(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHafalanTahfidz::route('/'),
            'create' => CreateHafalanTahfidz::route('/create'),
            'edit' => EditHafalanTahfidz::route('/{record}/edit'),
        ];
    }
}
