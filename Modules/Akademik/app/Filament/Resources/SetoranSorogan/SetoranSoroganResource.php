<?php

namespace Modules\Akademik\Filament\Resources\SetoranSorogan;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Akademik\Filament\Resources\SetoranSorogan\Pages\CreateSetoranSorogan;
use Modules\Akademik\Filament\Resources\SetoranSorogan\Pages\EditSetoranSorogan;
use Modules\Akademik\Filament\Resources\SetoranSorogan\Pages\ListSetoranSorogan;
use Modules\Akademik\Filament\Resources\SetoranSorogan\Pages\ProgresSorogan;
use Modules\Akademik\Models\Kitab;
use Modules\Akademik\Models\SetoranSorogan;
use UnitEnum;

/**
 * Issue #330 — Setoran Sorogan: penilaian baca kitab individual per santri
 * (kelancaran, ketepatan makna/ngabsahi, nahwu/i'rab). Pola mengikuti
 * HafalanTahfidzResource; gerbang modul & permission menumpang 'akademik'
 * (satu domain dengan Kitab/KajianKitab — tanpa registry modul baru).
 */
class SetoranSoroganResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = SetoranSorogan::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Setoran Sorogan';

    protected static UnitEnum|string|null $navigationGroup = 'Ngaji Kitab';

    protected static ?string $modelLabel = 'Setoran Sorogan';

    protected static ?string $pluralModelLabel = 'Setoran Sorogan';

    protected static ?string $slug = 'setoran-sorogan';

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Setoran')->schema([
                    Select::make('santri_id')
                        ->label('Santri')
                        ->relationship('santri', 'nama')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('kitab_id')
                        ->label('Kitab')
                        ->relationship('kitab', 'nama')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Kitab diambil dari Master Akademik → Kitab.'),
                    TextInput::make('bab')
                        ->label('Bab / Fasal')
                        ->placeholder('Bab Thaharah')
                        ->maxLength(255),
                    TextInput::make('halaman_dari')
                        ->label('Halaman Dari')
                        ->numeric()
                        ->minValue(1),
                    TextInput::make('halaman_sampai')
                        ->label('Halaman Sampai')
                        ->numeric()
                        ->minValue(1)
                        ->gte('halaman_dari'),
                    DatePicker::make('tanggal')
                        ->label('Tanggal')
                        ->default(now())
                        ->required(),
                    Select::make('ustadz_id')
                        ->label('Ustadz Penyimak')
                        ->relationship('ustadz', 'name')
                        ->searchable()
                        ->preload()
                        ->default(fn () => auth()->id()),
                    Select::make('metode')
                        ->label('Metode')
                        ->options(SetoranSorogan::METODE)
                        ->default('sorogan')
                        ->required(),
                    Select::make('status')
                        ->label('Status')
                        ->options(SetoranSorogan::STATUS)
                        ->default('lanjut')
                        ->required()
                        ->helperText('Khatam = kitab selesai dibaca sampai akhir.'),
                ])->columns(2),
                Section::make('Penilaian (0–100)')->schema([
                    TextInput::make('nilai_kelancaran')
                        ->label('Kelancaran Baca')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100),
                    TextInput::make('nilai_makna')
                        ->label('Ketepatan Makna (Ngabsahi)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100),
                    TextInput::make('nilai_nahwu')
                        ->label("Kedudukan Nahwu / I'rab")
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100),
                    Textarea::make('catatan')
                        ->label('Catatan Ustadz')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada setoran sorogan')
            ->emptyStateDescription('Catat setoran baca kitab santri (sorogan) beserta penilaiannya di sini.')
            ->emptyStateIcon('heroicon-o-book-open')
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->sortable()->searchable(),
                TextColumn::make('kitab.nama')->label('Kitab')->sortable()->searchable(),
                TextColumn::make('bab')->label('Bab')->toggleable(),
                TextColumn::make('halaman_dari')->label('Hal.')
                    ->state(fn (SetoranSorogan $r): string => $r->halaman_dari !== null
                        ? $r->halaman_dari.($r->halaman_sampai !== null && $r->halaman_sampai !== $r->halaman_dari ? '–'.$r->halaman_sampai : '')
                        : '—'),
                TextColumn::make('tanggal')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('ustadz.name')->label('Penyimak')->toggleable(),
                TextColumn::make('metode')->label('Metode')->badge(),
                TextColumn::make('nilai_rata')->label('Nilai')->placeholder('—'),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'khatam' => 'success',
                        'ulang' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('kitab_id')
                    ->label('Kitab')
                    ->options(fn () => Kitab::query()->orderBy('nama')->pluck('nama', 'id')->all()),
                SelectFilter::make('metode')->options(SetoranSorogan::METODE),
                SelectFilter::make('status')->options(SetoranSorogan::STATUS),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSetoranSorogan::route('/'),
            'create' => CreateSetoranSorogan::route('/create'),
            'edit' => EditSetoranSorogan::route('/{record}/edit'),
            'progres' => ProgresSorogan::route('/progres'),
        ];
    }
}
