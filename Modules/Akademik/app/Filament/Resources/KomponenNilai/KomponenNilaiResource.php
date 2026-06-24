<?php

namespace Modules\Akademik\Filament\Resources\KomponenNilai;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Akademik\Filament\Resources\KomponenNilai\Pages\CreateKomponenNilai;
use Modules\Akademik\Filament\Resources\KomponenNilai\Pages\EditKomponenNilai;
use Modules\Akademik\Filament\Resources\KomponenNilai\Pages\ListKomponenNilai;
use Modules\Akademik\Models\KomponenNilai;
use Modules\Akademik\Models\Pelajaran;

/**
 * Konfigurasi komponen + bobot nilai per pesantren (gap-85).
 *
 * pelajaran_id NULL = default tenant (dipakai semua pelajaran);
 * pelajaran_id terisi = override khusus pelajaran tersebut. Bobot dibaca
 * NilaiService::komponenUntukPelajaran() saat menghitung nilai akhir.
 */
class KomponenNilaiResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = KomponenNilai::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Bobot Nilai';

    protected static string|\UnitEnum|null $navigationGroup = 'Nilai & Rapor';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Komponen Nilai';

    protected static ?string $pluralModelLabel = 'Komponen Nilai';

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
            Section::make('Komponen Nilai')
                ->description('Atur komponen penilaian dan bobotnya. Total bobot per pelajaran (atau default tenant) sebaiknya 100%. Kosongkan Pelajaran untuk berlaku ke semua pelajaran.')
                ->schema([
                    Select::make('pelajaran_id')
                        ->label('Pelajaran')
                        ->helperText('Kosongkan = bobot default berlaku untuk semua pelajaran.')
                        ->options(fn () => Pelajaran::query()->orderBy('nama')->pluck('nama', 'id'))
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    TextInput::make('nama')
                        ->label('Nama komponen')
                        ->placeholder('mis. Harian, UTS, UAS')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('bobot')
                        ->label('Bobot (%)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->required()
                        ->suffix('%'),
                    TextInput::make('urutan')
                        ->label('Urutan')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada komponen nilai')
            ->emptyStateDescription('Tambahkan komponen (mis. Harian, UTS, UAS) beserta bobotnya agar nilai akhir dihitung sesuai kebijakan pesantren.')
            ->emptyStateIcon('heroicon-o-scale')
            ->emptyStateActions([
                Action::make('tambah')
                    ->label('Tambah Komponen')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => static::canCreate())
                    ->url(fn (): string => static::getUrl('create')),
            ])
            ->columns([
                TextColumn::make('pelajaran.nama')
                    ->label('Pelajaran')
                    ->placeholder('Semua pelajaran (default)')
                    ->sortable(),
                TextColumn::make('nama')->label('Komponen')->searchable()->sortable(),
                TextColumn::make('bobot')->label('Bobot')->suffix('%')->sortable(),
                TextColumn::make('urutan')->label('Urutan')->sortable(),
            ])
            ->defaultSort('urutan')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKomponenNilai::route('/'),
            'create' => CreateKomponenNilai::route('/create'),
            'edit' => EditKomponenNilai::route('/{record}/edit'),
        ];
    }
}
