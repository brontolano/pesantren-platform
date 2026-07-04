<?php

namespace Modules\Asrama\Filament\Resources\PenempatanAsrama;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Forms\SantriSelect;
use App\Support\UiMode;
use Filament\Actions\Action;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Asrama\Filament\Resources\PenempatanAsrama\Pages\CreatePenempatanAsrama;
use Modules\Asrama\Filament\Resources\PenempatanAsrama\Pages\EditPenempatanAsrama;
use Modules\Asrama\Filament\Resources\PenempatanAsrama\Pages\ListPenempatanAsrama;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Asrama\Models\PenempatanAsrama;

class PenempatanAsramaResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = PenempatanAsrama::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Penempatan Asrama';

    protected static string|\UnitEnum|null $navigationGroup = 'Asrama & Pengasuhan';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('asrama') && (auth()->user()?->canAny(['view_asrama', 'manage_asrama']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('asrama') && (auth()->user()?->canAny(['view_asrama', 'manage_asrama']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_asrama') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('manage_asrama') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            SantriSelect::make('santri_id')
                ->required(),
            Select::make('kamar_id')
                ->label('Kamar')
                ->options(fn () => KamarAsrama::query()
                    ->with('blok')
                    ->get()
                    ->mapWithKeys(fn (KamarAsrama $kamar) => [$kamar->id => $kamar->blok->nama_blok.' - '.$kamar->nomor_kamar]))
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
            ->emptyStateHeading('Belum ada penempatan')
            ->emptyStateDescription('Tempatkan santri ke kamar asrama untuk mulai mengelola hunian.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateActions([
                Action::make('tambah')
                    ->label('Tambah Penempatan')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => static::canCreate())
                    ->url(fn (): string => static::getUrl('create')),
            ])
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

    public static function getEloquentQuery(): Builder
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
