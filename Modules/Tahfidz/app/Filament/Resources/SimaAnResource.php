<?php

namespace Modules\Tahfidz\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Forms\SantriSelect;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Tahfidz\Filament\Resources\SimaAnResource\Pages\CreateSimaAn;
use Modules\Tahfidz\Filament\Resources\SimaAnResource\Pages\EditSimaAn;
use Modules\Tahfidz\Filament\Resources\SimaAnResource\Pages\ListSimaAn;
use Modules\Tahfidz\Models\SimaAn;
use UnitEnum;

/**
 * Sima'an (penyimakan hafalan) — kini hanya ditulis dari PWA Guru. Resource ini
 * agar staf bisa LIHAT/KOREKSI/menambah data sima'an dari panel (audit & perbaikan).
 * client_ref (idempotency PWA) TIDAK diisi manual — biarkan null untuk entri panel.
 */
class SimaAnResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = SimaAn::class;

    protected static ?string $slug = 'tahfidz/simaan';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-microphone';

    protected static ?string $navigationLabel = "Sima'an";

    protected static ?string $modelLabel = "Sima'an";

    protected static ?string $pluralModelLabel = "Sima'an";

    protected static UnitEnum|string|null $navigationGroup = 'Tahfidz';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('tahfidz') && (auth()->user()?->canAny(['view_tahfidz', 'manage_tahfidz']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('tahfidz') && (auth()->user()?->canAny(['view_tahfidz', 'manage_tahfidz']) ?? false);
    }

    /**
     * Gerbang mutasi (audit RBAC 2026-07 P0-1): `view_*` = baca-saja;
     * create/edit/delete WAJIB permission `manage_tahfidz`.
     */
    public static function canCreate(): bool
    {
        return static::canAccess() && (auth()->user()?->can('manage_tahfidz') ?? false);
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canCreate();
    }

    public static function canDeleteAny(): bool
    {
        return static::canCreate();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()->schema([
                SantriSelect::make('santri_id')
                    ->required(),
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->default(now())
                    ->required(),
                TextInput::make('rentang')
                    ->label('Rentang (Juz / Surah-Ayat)')
                    ->placeholder('mis. Juz 1 atau Al-Baqarah 1-50')
                    ->required()
                    ->maxLength(255),
                Select::make('penyimak_id')
                    ->label('Penyimak')
                    ->relationship('penyimak', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('nilai_kelancaran')
                    ->label('Nilai Kelancaran')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),
                TextInput::make('nilai_tajwid')
                    ->label('Nilai Tajwid')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),
                Textarea::make('catatan')
                    ->label('Catatan')
                    ->rows(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading("Belum ada sima'an")
            ->emptyStateDescription("Sima'an yang dicatat lewat PWA Guru tampil di sini; bisa dikoreksi/ditambah dari panel.")
            ->emptyStateIcon('heroicon-o-microphone')
            ->columns([
                TextColumn::make('tanggal')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('santri.nama')->label('Santri')->searchable()->sortable(),
                TextColumn::make('rentang')->label('Rentang')->limit(30),
                TextColumn::make('penyimak.name')->label('Penyimak')->toggleable(),
                TextColumn::make('nilai_kelancaran')->label('Kelancaran')->placeholder('—'),
                TextColumn::make('nilai_tajwid')->label('Tajwid')->placeholder('—'),
                TextColumn::make('catatan')->label('Catatan')->limit(30)->toggleable(),
            ])
            ->actions([EditAction::make()])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSimaAn::route('/'),
            'create' => CreateSimaAn::route('/create'),
            'edit' => EditSimaAn::route('/{record}/edit'),
        ];
    }
}
