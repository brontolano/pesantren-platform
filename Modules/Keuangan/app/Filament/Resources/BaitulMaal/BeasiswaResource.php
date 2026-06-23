<?php

namespace Modules\Keuangan\Filament\Resources\BaitulMaal;

use App\Filament\Concerns\ModuleGate;
use Modules\Keuangan\Filament\Resources\BaitulMaal\Pages\CreateBeasiswa;
use Modules\Keuangan\Filament\Resources\BaitulMaal\Pages\EditBeasiswa;
use Modules\Keuangan\Filament\Resources\BaitulMaal\Pages\ListBeasiswa;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Beasiswa;
use Modules\Ledger\Models\SumberDana;

/**
 * 2c — Beasiswa potong SPP (dana zakat/infaq/wakaf). Potongan diterapkan
 * via mekanisme diskon tagihan existing oleh BeasiswaService.
 */
class BeasiswaResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Beasiswa::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Beasiswa';

    protected static ?string $modelLabel = 'Beasiswa';

    protected static ?string $pluralModelLabel = 'Beasiswa';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 32;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('keuangan') && (auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_keuangan') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('manage_keuangan') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Beasiswa')->schema([
                Select::make('santri_id')->label('Santri')->required()->searchable()
                    ->options(fn () => Santri::query()->where('status', 'aktif')->orderBy('nama')->pluck('nama', 'id')),
                Select::make('sumber_dana_id')->label('Sumber Dana Penanggung')->required()->searchable()
                    ->options(fn () => SumberDana::query()->orderBy('kode_dana')->pluck('nama_sumber', 'id')),
                TextInput::make('nominal_per_bulan')->label('Potongan per Bulan (Rp)')->numeric()->required()->prefix('Rp'),
                Toggle::make('aktif')->label('Aktif')->default(true)->inline(false),
                DatePicker::make('mulai')->label('Mulai')->required(),
                DatePicker::make('selesai')->label('Selesai (opsional)'),
                TextInput::make('keterangan')->label('Keterangan')->maxLength(255)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->searchable(),
                TextColumn::make('sumberDana.nama_sumber')->label('Sumber Dana'),
                TextColumn::make('nominal_per_bulan')->label('Potongan/Bulan')->money('IDR'),
                TextColumn::make('mulai')->label('Mulai')->date('d M Y'),
                TextColumn::make('selesai')->label('Selesai')->date('d M Y')->placeholder('—'),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBeasiswa::route('/'),
            'create' => CreateBeasiswa::route('/create'),
            'edit' => EditBeasiswa::route('/{record}/edit'),
        ];
    }
}
