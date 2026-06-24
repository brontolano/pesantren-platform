<?php

namespace Modules\Ledger\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use Modules\Ledger\Filament\Resources\Pages\CreateSumberDana;
use Modules\Ledger\Filament\Resources\Pages\EditSumberDana;
use Modules\Ledger\Filament\Resources\Pages\ListSumberDana;
use App\Support\UiMode;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Ledger\Models\SumberDana;

class SumberDanaResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = SumberDana::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationLabel = 'Sumber Dana';

    protected static ?string $modelLabel = 'Sumber Dana';

    protected static ?string $pluralModelLabel = 'Sumber Dana';

    protected static string|\UnitEnum|null $navigationGroup = 'Buku Besar';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('ledger') && (auth()->user()?->canAny(['view_ledger', 'manage_ledger']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('ledger') && (auth()->user()?->canAny(['view_ledger', 'manage_ledger']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_ledger') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('manage_ledger') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('manage_ledger') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sumber Dana (Dimensi Multi-Fund)')->schema([
                TextInput::make('kode_dana')->label('Kode')->required()->maxLength(10),
                TextInput::make('nama_sumber')->label('Nama Sumber')->required()->maxLength(255),
                Select::make('kategori')->label('Kategori')->options([
                    'negara' => 'Negara (BOS/BOP)',
                    'komersial' => 'Komersial / Swadaya',
                    'sosial' => 'Sosial (Wakaf/ZIS)',
                ])->required(),
                Select::make('rezim')->label('Rezim (BOS vs BOP)')->options([
                    'bos_madrasah' => 'BOS Madrasah',
                    'bop_pesantren' => 'BOP Pesantren',
                ])->placeholder('— bukan dana negara —'),
                Select::make('kehalalan')->label('Kehalalan')->options([
                    'halal' => 'Halal',
                    'syubhat' => 'Syubhat',
                    'non_halal' => 'Non-Halal',
                ])->default('halal')->required()
                    ->helperText('Dana syubhat/non-halal diperlakukan sebagai Dana Kebajikan (bukan pendapatan).'),
                Toggle::make('is_dana_kebajikan')->label('Dana Kebajikan / Pembersihan'),
                Toggle::make('is_wakaf')->label('Dana Wakaf (terkunci dari agunan)'),
                Toggle::make('is_terikat')->label('Dana Terikat (PSAK 112/109)'),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_dana')->label('Kode')->sortable()->searchable(),
                TextColumn::make('nama_sumber')->label('Nama')->searchable(),
                TextColumn::make('kategori')->label('Kategori')->badge(),
                TextColumn::make('kehalalan')->label('Kehalalan')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'halal' => 'success',
                        'syubhat' => 'warning',
                        'non_halal' => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('is_wakaf')->label('Wakaf')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('kode_dana')
            ->filters([
                SelectFilter::make('kategori')->options([
                    'negara' => 'Negara', 'komersial' => 'Komersial', 'sosial' => 'Sosial',
                ]),
                SelectFilter::make('kehalalan')->options([
                    'halal' => 'Halal', 'syubhat' => 'Syubhat', 'non_halal' => 'Non-Halal',
                ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSumberDana::route('/'),
            'create' => CreateSumberDana::route('/create'),
            'edit' => EditSumberDana::route('/{record}/edit'),
        ];
    }
}
