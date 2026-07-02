<?php

namespace Modules\Keuangan\Filament\Resources\Tagihan;

use App\Filament\Concerns\ModuleGate;
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
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Filament\Resources\Tagihan\Pages\CreateTagihan;
use Modules\Keuangan\Filament\Resources\Tagihan\Pages\EditTagihan;
use Modules\Keuangan\Filament\Resources\Tagihan\Pages\ListTagihan;
use Modules\Keuangan\Models\Tagihan;

class TagihanResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Tagihan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Tagihan';

    protected static ?string $modelLabel = 'Tagihan';

    protected static ?string $pluralModelLabel = 'Tagihan';

    protected static string|\UnitEnum|null $navigationGroup = 'Tagihan Santri';

    protected static ?int $navigationSort = 1;

    /** Badge: jumlah tagihan yang belum lunas — sinyal kerja bendahara. */
    public static function getNavigationBadge(): ?string
    {
        try {
            $n = Tagihan::query()->whereIn('status', ['belum_bayar', 'sebagian'])->count();

            return $n > 0 ? (string) $n : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Tagihan belum lunas';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('keuangan') && (auth()->user()?->canAny(['view_tagihan', 'manage_tagihan']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('keuangan') && (auth()->user()?->canAny(['view_tagihan', 'manage_tagihan']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_tagihan') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('manage_tagihan') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('manage_tagihan') ?? false;
    }

    /**
     * Atribut global search: cari tagihan via nama / NIS santri (relasi),
     * plus jenis tagihan. Read-only — tak menyentuh logika uang.
     *
     * @return array<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['santri.nama', 'santri.nis', 'jenis_tagihan'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return (string) ($record->santri?->nama ?? 'Tagihan');
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'NIS' => (string) ($record->santri?->nis ?? '—'),
            'Jenis' => (string) ($record->jenis_tagihan ?? '—'),
            'Nominal' => 'Rp '.number_format((float) ($record->nominal_akhir ?? 0), 0, ',', '.'),
            'Status' => (string) ($record->status ?? '—'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Tagihan')->schema([
                Select::make('santri_id')
                    ->label('Santri')
                    ->options(fn () => Santri::where('status', 'aktif')->pluck('nama', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('jenis_tagihan')
                    ->label('Jenis Tagihan')
                    ->options([
                        'spp' => 'SPP',
                        'infaq' => 'Infaq',
                        'seragam' => 'Seragam',
                        'kitab' => 'Kitab',
                        'daftar_ulang' => 'Daftar Ulang',
                        'lainnya' => 'Lainnya',
                    ])
                    ->required(),
                TextInput::make('keterangan')
                    ->label('Keterangan')
                    ->maxLength(255),
                TextInput::make('nominal')
                    ->label('Nominal')
                    ->numeric()
                    ->prefix('Rp')
                    ->required()
                    ->minValue(0),
                TextInput::make('diskon')
                    ->label('Diskon')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->minValue(0),
                DatePicker::make('jatuh_tempo')
                    ->label('Jatuh Tempo')
                    ->required(),
                TextInput::make('periode_akademik')
                    ->label('Periode Akademik')
                    ->placeholder('2026/2027')
                    ->maxLength(20),
                TextInput::make('bulan_masehi')
                    ->label('Bulan Masehi')
                    ->placeholder('2026-07')
                    ->maxLength(7),
                TextInput::make('bulan_hijriah')
                    ->label('Bulan Hijriah')
                    ->placeholder('Dzulhijjah 1447')
                    ->maxLength(50),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'belum_bayar' => 'Belum Bayar',
                        'sebagian' => 'Sebagian',
                        'lunas' => 'Lunas',
                        'batal' => 'Batal',
                    ])
                    ->default('belum_bayar')
                    ->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada tagihan')
            ->emptyStateDescription('Buat tagihan SPP atau biaya lain untuk santri di sini.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->columns([
                TextColumn::make('santri.nama')
                    ->label('Santri')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('santri.nis')
                    ->label('NIS')
                    ->searchable(),
                TextColumn::make('jenis_tagihan')
                    ->label('Jenis')
                    ->badge()
                    ->sortable(),
                TextColumn::make('nominal_akhir')
                    ->label('Nominal')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('jatuh_tempo')
                    ->label('Jatuh Tempo')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('bulan_masehi')
                    ->label('Bulan')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'belum_bayar' => 'danger',
                        'sebagian' => 'warning',
                        'lunas' => 'success',
                        'batal' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('jatuh_tempo', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'belum_bayar' => 'Belum Bayar',
                        'sebagian' => 'Sebagian',
                        'lunas' => 'Lunas',
                        'batal' => 'Batal',
                    ]),
                SelectFilter::make('jenis_tagihan')
                    ->options([
                        'spp' => 'SPP',
                        'infaq' => 'Infaq',
                        'seragam' => 'Seragam',
                        'kitab' => 'Kitab',
                        'daftar_ulang' => 'Daftar Ulang',
                        'lainnya' => 'Lainnya',
                    ]),
            ])
            ->actions(static::rowActions())
            // A5 — menu klik-kanan (leek/filament-right-click): MEMAKAI ULANG
            // action baris yang SAMA (Edit/Hapus) — nol logika/izin baru;
            // otorisasi Filament (canEdit/canDelete) tetap berlaku.
            ->contextMenuActions(static::rowActions());
    }

    /**
     * Action baris tabel Tagihan — SATU sumber dipakai ulang oleh ->actions()
     * DAN ->contextMenuActions() (menu klik-kanan A5). Nol logika baru.
     *
     * @return array<int, \Filament\Actions\Action>
     */
    protected static function rowActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('santri');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTagihan::route('/'),
            'create' => CreateTagihan::route('/create'),
            'edit' => EditTagihan::route('/{record}/edit'),
        ];
    }
}
