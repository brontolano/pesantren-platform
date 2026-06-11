<?php

namespace App\Filament\Resources\Tahfidz\Halaqah;

use App\Filament\Resources\Tahfidz\Halaqah\Pages\CreateHalaqah;
use App\Filament\Resources\Tahfidz\Halaqah\Pages\EditHalaqah;
use App\Filament\Resources\Tahfidz\Halaqah\Pages\ListHalaqah;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Tahfidz\Models\Halaqah;
use UnitEnum;

class HalaqahResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = Halaqah::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Halaqah';

    protected static UnitEnum|string|null $navigationGroup = 'Tahfidz';

    protected static ?string $modelLabel = 'Halaqah';

    protected static ?string $pluralModelLabel = 'Halaqah';

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('tahfidz');
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('tahfidz');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()->schema([
                    TextInput::make('nama')
                        ->label('Nama Halaqah')
                        ->required()
                        ->maxLength(255),
                    Select::make('musyrif_id')
                        ->label('Musyrif')
                        ->options(fn () => User::query()
                            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['guru', 'guru_tahfidz', 'super_admin']))
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Select::make('jenis')
                        ->label('Jenis')
                        ->options([
                            'tahfidz' => 'Tahfidz',
                            'kitab' => 'Kitab',
                            'diniyah' => 'Diniyah',
                        ])
                        ->default('tahfidz')
                        ->required(),
                    Toggle::make('aktif')
                        ->label('Aktif')
                        ->default(true),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada halaqah')
            ->emptyStateDescription('Buat halaqah untuk mengelompokkan santri binaan per musyrif.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->columns([
                TextColumn::make('nama')->label('Nama')->sortable()->searchable(),
                TextColumn::make('musyrif.name')->label('Musyrif')->sortable()->searchable(),
                TextColumn::make('jenis')->label('Jenis')->badge()->sortable(),
                TextColumn::make('keanggotaan_aktif_count')
                    ->label('Anggota Aktif')
                    ->counts('keanggotaanAktif'),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
                TextColumn::make('updated_at')->label('Diubah')->since(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHalaqah::route('/'),
            'create' => CreateHalaqah::route('/create'),
            'edit' => EditHalaqah::route('/{record}/edit'),
        ];
    }
}
