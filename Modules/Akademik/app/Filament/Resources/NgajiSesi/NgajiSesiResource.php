<?php

namespace Modules\Akademik\Filament\Resources\NgajiSesi;

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
use Modules\Akademik\Filament\Resources\NgajiSesi\Pages\CreateNgajiSesi;
use Modules\Akademik\Filament\Resources\NgajiSesi\Pages\EditNgajiSesi;
use Modules\Akademik\Filament\Resources\NgajiSesi\Pages\ListNgajiSesi;
use Modules\Akademik\Filament\Resources\NgajiSesi\RelationManagers\PesertaRelationManager;
use Modules\Akademik\Models\Kitab;
use Modules\Akademik\Models\NgajiSesi;
use UnitEnum;

/**
 * Issue #330 — Sesi Ngaji Kitab massal (bandongan/halaqah): satu sesi
 * (kitab, bab, tanggal, ustadz) + peserta banyak santri (kehadiran + nilai
 * ringkas) via relation manager — pola HalaqahResource + Keanggotaan.
 */
class NgajiSesiResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = NgajiSesi::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Sesi Bandongan/Halaqah';

    protected static UnitEnum|string|null $navigationGroup = 'Ngaji Kitab';

    protected static ?string $modelLabel = 'Sesi Ngaji Kitab';

    protected static ?string $pluralModelLabel = 'Sesi Ngaji Kitab';

    protected static ?string $slug = 'ngaji-sesi';

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
                Section::make()->schema([
                    Select::make('kitab_id')
                        ->label('Kitab')
                        ->relationship('kitab', 'nama')
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('bab')
                        ->label('Bab / Fasal')
                        ->placeholder('Bab Muamalah')
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
                        ->label('Ustadz Pengampu')
                        ->relationship('ustadz', 'name')
                        ->searchable()
                        ->preload()
                        ->default(fn () => auth()->id()),
                    Select::make('metode')
                        ->label('Metode')
                        ->options(NgajiSesi::METODE)
                        ->default('bandongan')
                        ->required(),
                    Textarea::make('catatan')
                        ->label('Catatan')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada sesi ngaji kitab')
            ->emptyStateDescription('Catat sesi bandongan/halaqah (kelas massal), lalu tambahkan peserta di halaman sesi.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->columns([
                TextColumn::make('kitab.nama')->label('Kitab')->sortable()->searchable(),
                TextColumn::make('bab')->label('Bab')->toggleable(),
                TextColumn::make('tanggal')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('ustadz.name')->label('Ustadz'),
                TextColumn::make('metode')->label('Metode')->badge(),
                TextColumn::make('peserta_count')->label('Peserta')->counts('peserta'),
            ])
            ->filters([
                SelectFilter::make('kitab_id')
                    ->label('Kitab')
                    ->options(fn () => Kitab::query()->orderBy('nama')->pluck('nama', 'id')->all()),
                SelectFilter::make('metode')->options(NgajiSesi::METODE),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            PesertaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNgajiSesi::route('/'),
            'create' => CreateNgajiSesi::route('/create'),
            'edit' => EditNgajiSesi::route('/{record}/edit'),
        ];
    }
}
