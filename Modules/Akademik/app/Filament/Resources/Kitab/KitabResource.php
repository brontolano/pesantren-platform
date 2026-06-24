<?php

namespace Modules\Akademik\Filament\Resources\Kitab;

use App\Filament\Concerns\ModuleGate;
use Modules\Akademik\Filament\Resources\Kitab\Pages\CreateKitab;
use Modules\Akademik\Filament\Resources\Kitab\Pages\EditKitab;
use Modules\Akademik\Filament\Resources\Kitab\Pages\ListKitab;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Akademik\Models\Kitab;
use UnitEnum;

class KitabResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Kitab::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Kitab';

    protected static UnitEnum|string|null $navigationGroup = 'Akademik & Tahfidz';

    protected static ?string $modelLabel = 'Kitab';

    protected static ?string $pluralModelLabel = 'Kitab';

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('akademik');
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('akademik');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()->schema([
                    TextInput::make('nama')
                        ->label('Nama Kitab')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('pengarang')
                        ->label('Pengarang')
                        ->maxLength(255),
                    TextInput::make('kategori')
                        ->label('Kategori / Fan')
                        ->placeholder('fiqih, nahwu, akhlak, ...')
                        ->maxLength(255),
                    TextInput::make('jumlah_halaman')
                        ->label('Jumlah Halaman')
                        ->numeric()
                        ->minValue(1),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada kitab')
            ->emptyStateDescription('Daftarkan kitab yang dikaji di pesantren.')
            ->emptyStateIcon('heroicon-o-book-open')
            ->columns([
                TextColumn::make('nama')->label('Nama')->sortable()->searchable(),
                TextColumn::make('pengarang')->label('Pengarang')->searchable(),
                TextColumn::make('kategori')->label('Kategori')->badge()->sortable(),
                TextColumn::make('jumlah_halaman')->label('Halaman'),
                TextColumn::make('updated_at')->label('Diubah')->since(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKitab::route('/'),
            'create' => CreateKitab::route('/create'),
            'edit' => EditKitab::route('/{record}/edit'),
        ];
    }
}
