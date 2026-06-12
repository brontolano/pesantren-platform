<?php

namespace App\Filament\Resources\Asrama\PenempatanAsrama;

use App\Filament\Resources\Asrama\PenempatanAsrama\Pages\CreatePenempatanAsrama;
use App\Filament\Resources\Asrama\PenempatanAsrama\Pages\EditPenempatanAsrama;
use App\Filament\Resources\Asrama\PenempatanAsrama\Pages\ListPenempatanAsrama;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Asrama\Models\PenempatanAsrama;
use Modules\Kesantrian\Models\Santri;

class PenempatanAsramaResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;
    protected static ?string $model = PenempatanAsrama::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Penempatan Asrama';

    protected static string|\UnitEnum|null $navigationGroup = 'Kesantrian';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('asrama') && (auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('asrama') && (auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('santri_id')
                ->label('Santri')
                ->options(fn () => Santri::query()->orderBy('nama')->pluck('nama', 'id'))
                ->required()
                ->searchable(),
            Select::make('kamar_id')
                ->label('Kamar')
                ->options(fn () => KamarAsrama::query()
                    ->with('blok')
                    ->get()
                    ->mapWithKeys(fn (KamarAsrama $kamar) => [$kamar->id => $kamar->blok->nama_blok . ' - ' . $kamar->nomor_kamar]))
                ->required()
                ->searchable(),
            DatePicker::make('tanggal_masuk')->required(),
            DatePicker::make('tanggal_keluar'),
            TextInput::make('keterangan')->maxLength(255),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->searchable()->sortable(),
                TextColumn::make('kamar.blok.nama_blok')->label('Blok'),
                TextColumn::make('kamar.nomor_kamar')->label('Kamar'),
                TextColumn::make('tanggal_masuk')->date(),
                TextColumn::make('tanggal_keluar')->date()->placeholder('-'),
                IconColumn::make('is_current')->label('Aktif')->boolean(),
            ])
            ->defaultSort('tanggal_masuk', 'desc')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['santri', 'kamar.blok']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPenempatanAsrama::route('/'),
            'create' => CreatePenempatanAsrama::route('/create'),
            'edit' => EditPenempatanAsrama::route('/{record}/edit'),
        ];
    }
}

