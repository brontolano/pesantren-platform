<?php

namespace App\Filament\Resources\Akademik\KalenderAkademik;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Resources\Akademik\KalenderAkademik\Pages\CreateKalenderAkademik;
use App\Filament\Resources\Akademik\KalenderAkademik\Pages\EditKalenderAkademik;
use App\Filament\Resources\Akademik\KalenderAkademik\Pages\ListKalenderAkademik;
use App\Support\UiMode;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Akademik\Models\KalenderAkademik;
use Modules\Akademik\Models\Lembaga;
use UnitEnum;

/**
 * WP-02 — Kalender akademik: daftar + form (tampilan tabel cukup;
 * kalender visual TIDAK dibangun di WP ini).
 */
class KalenderAkademikResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = KalenderAkademik::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Kalender Akademik';

    protected static ?string $modelLabel = 'Entri Kalender';

    protected static ?string $pluralModelLabel = 'Kalender Akademik';

    protected static UnitEnum|string|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 1;

    public const JENIS_OPTIONS = [
        'libur_nasional' => 'Libur Nasional',
        'libur_pondok' => 'Libur Pondok',
        'ramadhan' => 'Ramadhan',
        'ujian' => 'Ujian',
        'pasaran' => 'Pasaran/Kilatan',
        'kegiatan' => 'Kegiatan',
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
            Section::make('Entri Kalender')->schema([
                TextInput::make('keterangan')
                    ->required()
                    ->maxLength(255),
                Select::make('jenis')
                    ->options(self::JENIS_OPTIONS)
                    ->default('kegiatan')
                    ->required()
                    ->helperText('Libur nasional/pondok/Ramadhan mengurangi hari efektif; ujian/pasaran/kegiatan tidak.'),
                DatePicker::make('tanggal_mulai')
                    ->label('Tanggal Mulai')
                    ->required(),
                DatePicker::make('tanggal_selesai')
                    ->label('Tanggal Selesai')
                    ->required()
                    ->afterOrEqual('tanggal_mulai'),
                Select::make('lembaga_id')
                    ->label('Lembaga')
                    ->options(fn () => Lembaga::query()->where('aktif', true)->pluck('nama', 'id'))
                    ->placeholder('Semua lembaga')
                    ->helperText('Kosongkan agar berlaku untuk semua lembaga.')
                    ->searchable()
                    ->nullable(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada entri kalender')
            ->emptyStateDescription('Libur nasional tahun berjalan dibuat otomatis saat provisioning — tambahkan libur pondok, Ramadhan, ujian, dan kegiatan di sini.')
            ->columns([
                TextColumn::make('tanggal_mulai')->date('d M Y')->sortable()->label('Mulai'),
                TextColumn::make('tanggal_selesai')->date('d M Y')->sortable()->label('Selesai'),
                TextColumn::make('jenis')->badge()
                    ->formatStateUsing(fn (string $state): string => self::JENIS_OPTIONS[$state] ?? $state)
                    ->color(fn (string $state): string => in_array($state, KalenderAkademik::JENIS_TIDAK_EFEKTIF, true) ? 'danger' : 'info'),
                TextColumn::make('keterangan')->searchable()->wrap(),
                TextColumn::make('lembaga.nama')->label('Lembaga')->placeholder('Semua lembaga'),
            ])
            ->defaultSort('tanggal_mulai')
            ->filters([
                SelectFilter::make('jenis')->options(self::JENIS_OPTIONS),
                SelectFilter::make('lembaga_id')
                    ->label('Lembaga')
                    ->options(fn () => Lembaga::query()->pluck('nama', 'id')),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('lembaga');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKalenderAkademik::route('/'),
            'create' => CreateKalenderAkademik::route('/create'),
            'edit' => EditKalenderAkademik::route('/{record}/edit'),
        ];
    }
}
