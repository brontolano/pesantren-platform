<?php

namespace App\Filament\Resources\Akademik\Pelajaran;

use App\Filament\Resources\Akademik\Pelajaran\Pages\CreatePelajaran;
use App\Filament\Resources\Akademik\Pelajaran\Pages\EditPelajaran;
use App\Filament\Resources\Akademik\Pelajaran\Pages\ListPelajaran;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Akademik\Models\Pelajaran;

class PelajaranResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;
    protected static ?string $model = Pelajaran::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Pelajaran';

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_akademik') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_akademik') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_akademik') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pelajaran')->schema([
                TextInput::make('nama')->required()->maxLength(255),
                TextInput::make('kode')->required()->maxLength(50),
                TextInput::make('sks')->numeric()->default(1)->required(),
                Select::make('guru_pengampu_id')
                    ->label('Guru Pengampu')
                    ->options(fn () => User::query()
                        ->whereHas('roles', fn ($query) => $query->whereIn('name', ['guru', 'guru_tahfidz', 'super_admin']))
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->searchable()->sortable(),
                TextColumn::make('kode')->searchable()->sortable(),
                TextColumn::make('sks')->sortable(),
                TextColumn::make('guruPengampu.name')->label('Guru')->sortable(),
            ])
            ->defaultSort('nama')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPelajaran::route('/'),
            'create' => CreatePelajaran::route('/create'),
            'edit' => EditPelajaran::route('/{record}/edit'),
        ];
    }
}

