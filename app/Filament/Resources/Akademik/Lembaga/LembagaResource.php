<?php

namespace App\Filament\Resources\Akademik\Lembaga;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Resources\Akademik\Lembaga\Pages\CreateLembaga;
use App\Filament\Resources\Akademik\Lembaga\Pages\EditLembaga;
use App\Filament\Resources\Akademik\Lembaga\Pages\ListLembaga;
use App\Models\User;
use App\Support\UiMode;
use BackedEnum;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Akademik\Models\Lembaga;
use Modules\Ledger\Models\DivisiUnit;
use UnitEnum;

class LembagaResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Lembaga::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Lembaga';

    protected static ?string $modelLabel = 'Lembaga';

    protected static ?string $pluralModelLabel = 'Lembaga';

    protected static UnitEnum|string|null $navigationGroup = 'Akademik & Tahfidz';

    protected static ?int $navigationSort = 0;

    public const JENIS_OPTIONS = [
        'formal' => 'Formal (MI/MTs/MA/SMP/SMA/SMK)',
        'diniyah' => 'Diniyah',
        'tahfidz' => 'Tahfidz',
        'pondok' => 'Pondok',
        'mahad_aly' => "Ma'had Aly",
        'unit_usaha' => 'Unit Usaha',
    ];

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_akademik') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('manage_akademik') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('manage_akademik') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Lembaga')->schema([
                TextInput::make('nama')
                    ->required()
                    ->maxLength(255),
                Select::make('jenis')
                    ->options(self::JENIS_OPTIONS)
                    ->default('pondok')
                    ->required(),
                TextInput::make('jenjang')
                    ->label('Jenjang')
                    ->placeholder('MI/MTs/MA/SMP/SMA/SMK | ula/wustha/ulya')
                    ->maxLength(50),
                Select::make('kepala_id')
                    ->label('Kepala Lembaga')
                    ->options(fn () => User::query()->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
            ])->columns(2),
            Section::make('Legalitas & Keuangan')->schema([
                TextInput::make('nomor_statistik')
                    ->label('Nomor Statistik (NSM/NPSN/NSPP)')
                    ->maxLength(255),
                TextInput::make('izin_operasional')
                    ->label('Izin Operasional')
                    ->maxLength(255),
                DatePicker::make('tanggal_izin')
                    ->label('Tanggal Izin'),
                Select::make('divisi_unit_id')
                    ->label('Divisi/Unit (Buku Besar)')
                    ->helperText('Opsional — menyambungkan laporan keuangan per unit.')
                    ->options(fn () => DivisiUnit::query()->where('is_active', true)->pluck('nama_divisi', 'id'))
                    ->searchable()
                    ->nullable(),
                Toggle::make('aktif')
                    ->label('Aktif')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada lembaga')
            ->emptyStateDescription('Lembaga default "Pondok" dibuat otomatis saat provisioning.')
            ->columns([
                TextColumn::make('nama')->searchable()->sortable(),
                TextColumn::make('jenis')->badge()
                    ->formatStateUsing(fn (string $state): string => self::JENIS_OPTIONS[$state] ?? $state),
                TextColumn::make('jenjang')->placeholder('-'),
                TextColumn::make('kepala.name')->label('Kepala')->placeholder('-'),
                TextColumn::make('divisiUnit.nama_divisi')->label('Divisi/Unit')->placeholder('-'),
                IconColumn::make('aktif')->boolean()->label('Aktif'),
            ])
            ->defaultSort('nama')
            // WP-12 Kesiapan EMIS — lembaga tanpa NSM/NSPP atau izin operasional.
            ->filters([
                Filter::make('tanpa_legalitas')
                    ->label('Tanpa NSM/NSPP atau IJOP')
                    ->query(fn (Builder $query): Builder => $query->where(
                        fn (Builder $q) => $q
                            ->whereNull('nomor_statistik')->orWhere('nomor_statistik', '')
                            ->orWhereNull('izin_operasional')->orWhere('izin_operasional', ''),
                    )),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['kepala', 'divisiUnit']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLembaga::route('/'),
            'create' => CreateLembaga::route('/create'),
            'edit' => EditLembaga::route('/{record}/edit'),
        ];
    }
}
