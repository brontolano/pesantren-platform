<?php

namespace App\Filament\Resources\PPDB;

use App\Filament\Resources\PPDB\HasilUjian\Pages\CreateHasilUjian;
use App\Filament\Resources\PPDB\HasilUjian\Pages\EditHasilUjian;
use App\Filament\Resources\PPDB\HasilUjian\Pages\ListHasilUjian;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\HasilUjian;

class HasilUjianResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = HasilUjian::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Hasil Ujian';

    protected static ?string $modelLabel = 'Hasil Ujian';

    protected static ?string $pluralModelLabel = 'Hasil Ujian';

    protected static string|\UnitEnum|null $navigationGroup = 'PPDB';

    protected static ?string $slug = 'ppdb/hasil-ujian';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('ppdb') && (static::canAccess());
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('ppdb') && (Auth::user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Hasil Ujian')->schema([
                Select::make('calon_santri_id')
                    ->label('Calon Santri')
                    ->options(fn () => CalonSantri::orderBy('nama')->pluck('nama', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('kategori_ujian')
                    ->label('Kategori Ujian')
                    ->required()
                    ->maxLength(100),
                TextInput::make('nilai')
                    ->label('Nilai')
                    ->required()
                    ->numeric(),
                Textarea::make('catatan')
                    ->label('Catatan')
                    ->rows(3),
            ])->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('calonSantri.nama')
                    ->label('Calon Santri')
                    ->searchable(),
                TextColumn::make('kategori_ujian')
                    ->label('Kategori')
                    ->sortable(),
                TextColumn::make('nilai')
                    ->label('Nilai')
                    ->sortable(),
                TextColumn::make('catatan')
                    ->label('Catatan')
                    ->limit(40),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('calonSantri');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHasilUjian::route('/'),
            'create' => CreateHasilUjian::route('/create'),
            'edit' => EditHasilUjian::route('/{record}/edit'),
        ];
    }
}

