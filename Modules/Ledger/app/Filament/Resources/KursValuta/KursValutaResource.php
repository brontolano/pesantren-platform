<?php

namespace Modules\Ledger\Filament\Resources\KursValuta;

use App\Filament\Concerns\ModuleGate;
use Modules\Ledger\Filament\Resources\KursValuta\Pages\CreateKursValuta;
use Modules\Ledger\Filament\Resources\KursValuta\Pages\EditKursValuta;
use Modules\Ledger\Filament\Resources\KursValuta\Pages\ListKursValuta;
use App\Support\UiMode;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Ledger\Models\KursValuta;

class KursValutaResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = KursValuta::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Kurs Valuta (Donor)';

    protected static ?string $modelLabel = 'Kurs Valuta';

    protected static ?string $pluralModelLabel = 'Kurs Valuta (Donor)';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 31;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('ledger') && (auth()->user()?->can('manage_ledger') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kurs Valuta')->schema([
                TextInput::make('mata_uang')->label('Mata Uang')->required()->maxLength(8)->placeholder('USD / SAR / EUR')
                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                TextInput::make('kurs')->label('Kurs (1 unit = Rp)')->numeric()->required()->minValue(0.0001)->prefix('Rp'),
                DatePicker::make('berlaku_tanggal')->label('Berlaku Tanggal')->required()->default(now()),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mata_uang')->label('Mata Uang')->badge()->searchable(),
                TextColumn::make('kurs')->label('Kurs')->money('IDR'),
                TextColumn::make('berlaku_tanggal')->label('Berlaku')->date()->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('berlaku_tanggal', 'desc')
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKursValuta::route('/'),
            'create' => CreateKursValuta::route('/create'),
            'edit' => EditKursValuta::route('/{record}/edit'),
        ];
    }
}
