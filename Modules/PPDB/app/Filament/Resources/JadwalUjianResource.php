<?php

namespace Modules\PPDB\Filament\Resources;

use Modules\PPDB\Filament\Resources\JadwalUjian\Pages\CreateJadwalUjian;
use Modules\PPDB\Filament\Resources\JadwalUjian\Pages\EditJadwalUjian;
use Modules\PPDB\Filament\Resources\JadwalUjian\Pages\ListJadwalUjian;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\PPDB\Models\JadwalUjian;

/**
 * Jadwal ujian PPDB per kategori (F3/EXAM-1). Dipakai kartu peserta digital.
 */
class JadwalUjianResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = JadwalUjian::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Jadwal Ujian';

    protected static ?string $modelLabel = 'Jadwal Ujian';

    protected static ?string $pluralModelLabel = 'Jadwal Ujian';

    protected static string|\UnitEnum|null $navigationGroup = 'Ujian Seleksi';

    protected static ?string $slug = 'ppdb/jadwal-ujian';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('ppdb') && static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('ppdb') && (Auth::user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Jadwal Ujian')->schema([
                TextInput::make('kategori_ujian')
                    ->label('Kategori Ujian')
                    ->required()
                    ->maxLength(100)
                    ->helperText('Selaras dgn kategori bank soal / hasil ujian.'),
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->required(),
                TimePicker::make('waktu_mulai')
                    ->label('Waktu Mulai')
                    ->seconds(false),
                TimePicker::make('waktu_selesai')
                    ->label('Waktu Selesai')
                    ->seconds(false),
                TextInput::make('ruang')
                    ->label('Ruang')
                    ->maxLength(100),
                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kategori_ujian')
                    ->label('Kategori')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
                TextColumn::make('waktu_mulai')
                    ->label('Mulai'),
                TextColumn::make('ruang')
                    ->label('Ruang')
                    ->placeholder('—'),
            ])
            ->defaultSort('tanggal', 'desc')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJadwalUjian::route('/'),
            'create' => CreateJadwalUjian::route('/create'),
            'edit' => EditJadwalUjian::route('/{record}/edit'),
        ];
    }
}
