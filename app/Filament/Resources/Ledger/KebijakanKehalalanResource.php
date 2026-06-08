<?php

namespace App\Filament\Resources\Ledger;

use App\Filament\Resources\Ledger\Pages\ListKebijakanKehalalan;
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
use Filament\Tables\Table;
use Modules\Ledger\Models\KebijakanKehalalan;
use Modules\Ledger\Support\FiturKeuangan;

/**
 * Editor kebijakan kehalalan (whitelist penyaluran dana syubhat).
 * WAJIB disahkan otoritas syar'i pesantren; panel ini hanya alat ubah data.
 */
class KebijakanKehalalanResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = KebijakanKehalalan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Kebijakan Kehalalan';

    protected static ?string $modelLabel = 'Kebijakan Kehalalan';

    protected static ?string $pluralModelLabel = 'Kebijakan Kehalalan';

    protected static string|\UnitEnum|null $navigationGroup = 'Buku Besar';

    protected static ?int $navigationSort = 21;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('ledger')
            && FiturKeuangan::pemisahanKehalalanAktif()
            && (auth()->user()?->can('manage_dana_kebajikan') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Aturan Penyaluran Dana Syubhat')->schema([
                Select::make('kehalalan_sumber')->label('Kelas Dana')->options([
                    'syubhat' => 'Syubhat', 'non_halal' => 'Non-Halal',
                ])->required(),
                Select::make('peruntukan_tujuan')->label('Peruntukan Tujuan')->options([
                    'fasilitas_umum' => 'Fasilitas Umum', 'dhuafa' => 'Dhuafa',
                    'operasional' => 'Operasional Non-Ibadah', 'ibadah' => 'Ibadah (biasanya dilarang)',
                    'umum' => 'Umum',
                ])->required(),
                Toggle::make('diizinkan')->label('Diizinkan'),
                TextInput::make('dasar')->label('Dasar / Catatan Syar\'i')->maxLength(255)->columnSpanFull(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kehalalan_sumber')->label('Kelas Dana')->badge(),
                TextColumn::make('peruntukan_tujuan')->label('Peruntukan')->badge(),
                IconColumn::make('diizinkan')->label('Diizinkan')->boolean(),
                TextColumn::make('dasar')->label('Dasar')->limit(50)->placeholder('—'),
            ])
            ->defaultSort('kehalalan_sumber')
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKebijakanKehalalan::route('/'),
        ];
    }
}
