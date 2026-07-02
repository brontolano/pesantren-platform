<?php

namespace Modules\Akademik\Filament\Resources\PeriodeAkademik;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Akademik\Filament\Resources\PeriodeAkademik\Pages\CreatePeriodeAkademik;
use Modules\Akademik\Filament\Resources\PeriodeAkademik\Pages\EditPeriodeAkademik;
use Modules\Akademik\Filament\Resources\PeriodeAkademik\Pages\ListPeriodeAkademik;
use Modules\Akademik\Models\Lembaga;
use Modules\Akademik\Models\PeriodeAkademik;

class PeriodeAkademikResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = PeriodeAkademik::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Periode Akademik';

    protected static ?string $modelLabel = 'Periode Akademik';

    protected static ?string $pluralModelLabel = 'Periode Akademik';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Akademik';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::navAdvanced('akademik_inti') && static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
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
            Section::make('Periode Akademik')->schema([
                TextInput::make('nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('tahun_akademik')
                    ->label('Tahun Akademik')
                    ->required()
                    ->placeholder('2026/2027')
                    ->maxLength(20),
                Select::make('semester')
                    ->options([
                        'ganjil' => 'Ganjil',
                        'genap' => 'Genap',
                        'pendek' => 'Pendek',
                    ])
                    ->default('ganjil')
                    ->required(),
                DatePicker::make('tanggal_mulai')
                    ->required(),
                DatePicker::make('tanggal_akhir')
                    ->required(),
                Select::make('lembaga_id')
                    ->label('Lembaga (opsional)')
                    ->helperText('Kosong = berlaku untuk lembaga default tenant.')
                    ->options(fn () => Lembaga::query()->where('aktif', true)->pluck('nama', 'id'))
                    ->searchable()
                    ->nullable(),
                Select::make('kalender')
                    ->label('Kalender')
                    ->options(['masehi' => 'Masehi', 'hijriyah' => 'Hijriyah'])
                    ->default('masehi')
                    ->required(),
                TextInput::make('tahun_hijriyah')
                    ->label('Tahun Hijriyah (label)')
                    ->placeholder('1448/1449')
                    ->maxLength(20),
                Select::make('is_active')
                    ->label('Status Periode')
                    ->options([1 => 'Aktif (periode berjalan)', 0 => 'Nonaktif (belum / sudah selesai)'])
                    ->helperText('Hanya satu periode yang berstatus Aktif. Set ke Nonaktif saat semester sudah selesai.')
                    ->default(0)
                    ->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->searchable()->sortable(),
                TextColumn::make('tahun_akademik')->sortable(),
                TextColumn::make('semester')->badge(),
                TextColumn::make('tanggal_mulai')->date('d M Y')->sortable(),
                TextColumn::make('tanggal_akhir')->date('d M Y')->sortable(),
                IconColumn::make('is_active')->boolean()->label('Aktif'),
            ])
            ->defaultSort('tanggal_mulai', 'desc')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeriodeAkademik::route('/'),
            'create' => CreatePeriodeAkademik::route('/create'),
            'edit' => EditPeriodeAkademik::route('/{record}/edit'),
        ];
    }
}
