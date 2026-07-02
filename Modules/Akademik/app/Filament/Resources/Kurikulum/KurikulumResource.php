<?php

namespace Modules\Akademik\Filament\Resources\Kurikulum;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Akademik\Filament\Resources\Kurikulum\Pages\CreateKurikulum;
use Modules\Akademik\Filament\Resources\Kurikulum\Pages\EditKurikulum;
use Modules\Akademik\Filament\Resources\Kurikulum\Pages\ListKurikulum;
use Modules\Akademik\Models\Kitab;
use Modules\Akademik\Models\Kurikulum;
use Modules\Akademik\Models\Lembaga;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Services\KurikulumService;
use Modules\Kesantrian\Models\Kelas;
use Throwable;
use UnitEnum;

/**
 * WP-06 — Kurikulum per Tingkat: daftar mapel per tingkat per lembaga,
 * aksi Buka Periode (generate Nilai kosong, idempoten) + Cetak PDF.
 */
class KurikulumResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Kurikulum::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Kurikulum';

    protected static ?string $modelLabel = 'Kurikulum';

    protected static ?string $pluralModelLabel = 'Kurikulum';

    protected static UnitEnum|string|null $navigationGroup = 'Master Akademik';

    protected static ?int $navigationSort = 2;

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

    /** Tingkat TIDAK di-hardcode: diturunkan dari data kelas tenant. */
    public static function tingkatOptions(): array
    {
        return Kelas::query()
            ->whereNotNull('tingkat')
            ->distinct()
            ->orderBy('tingkat')
            ->pluck('tingkat', 'tingkat')
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kurikulum')->schema([
                TextInput::make('nama')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Contoh: Kurikulum Diniyah 1447H'),
                Select::make('lembaga_id')
                    ->label('Lembaga')
                    ->options(fn () => Lembaga::query()->where('aktif', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->searchable()
                    ->required(),
                Toggle::make('aktif')
                    ->label('Aktif')
                    ->helperText('Hanya kurikulum aktif yang dipakai sebagai acuan validasi jadwal.')
                    ->default(true),
            ])->columns(2),
            Section::make('Mapel per Tingkat')->schema([
                Repeater::make('mapel')
                    ->label('Daftar Mapel')
                    ->relationship()
                    ->schema([
                        Select::make('tingkat')
                            ->label('Tingkat')
                            ->options(fn () => self::tingkatOptions())
                            ->searchable()
                            ->required(),
                        Select::make('pelajaran_id')
                            ->label('Mata Pelajaran')
                            ->options(fn () => Pelajaran::query()->orderBy('nama')->pluck('nama', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('kitab_id')
                            ->label('Kitab (opsional)')
                            ->options(fn () => Kitab::query()->orderBy('nama')->pluck('nama', 'id'))
                            ->searchable()
                            ->nullable(),
                        TextInput::make('jam_per_minggu')
                            ->label('Jam/Minggu')
                            ->numeric()
                            ->minValue(1)
                            ->default(2)
                            ->required(),
                        Toggle::make('is_wajib')
                            ->label('Wajib')
                            ->default(true),
                        TextInput::make('urutan')
                            ->label('Urutan')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->addActionLabel('Tambah Mapel'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada kurikulum')
            ->emptyStateDescription('Buat kurikulum per lembaga, lalu isi mapel per tingkat.')
            ->columns([
                TextColumn::make('nama')->searchable()->sortable(),
                TextColumn::make('lembaga.nama')->label('Lembaga')->sortable(),
                TextColumn::make('mapel_count')->counts('mapel')->label('Jumlah Mapel'),
                IconColumn::make('aktif')->boolean()->label('Aktif'),
            ])
            ->defaultSort('nama')
            ->actions([
                Action::make('buka_periode')
                    ->label('Buka Periode')
                    ->icon('heroicon-o-play')
                    ->requiresConfirmation()
                    ->modalHeading('Buka Periode')
                    ->modalDescription('Membuat baris nilai kosong per santri aktif × mapel wajib untuk periode akademik aktif lembaga ini. Aman diklik ulang (tidak dobel).')
                    ->visible(fn (): bool => auth()->user()?->can('manage_akademik') ?? false)
                    ->action(function (Kurikulum $record): void {
                        try {
                            $hasil = app(KurikulumService::class)->bukaPeriode($record);
                            Notification::make()
                                ->title('Periode dibuka')
                                ->body("Baris nilai baru: {$hasil['dibuat']}, sudah ada (dilewati): {$hasil['dilewati']}.")
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Gagal membuka periode')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('cetak_pdf')
                    ->label('Cetak PDF')
                    ->icon('heroicon-o-printer')
                    ->action(function (Kurikulum $record) {
                        $pdf = app(KurikulumService::class)->strukturPdf($record);
                        $filename = 'kurikulum-'.Str::slug($record->nama).'.pdf';

                        return response()->streamDownload(
                            fn () => print ($pdf),
                            $filename,
                            ['Content-Type' => 'application/pdf'],
                        );
                    }),
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
            'index' => ListKurikulum::route('/'),
            'create' => CreateKurikulum::route('/create'),
            'edit' => EditKurikulum::route('/{record}/edit'),
        ];
    }
}
