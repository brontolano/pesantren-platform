<?php

namespace Modules\Kesehatan\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Kesehatan\Filament\Resources\KunjunganUks\Pages\CreateKunjunganUks;
use Modules\Kesehatan\Filament\Resources\KunjunganUks\Pages\EditKunjunganUks;
use Modules\Kesehatan\Filament\Resources\KunjunganUks\Pages\ListKunjunganUks;
use Modules\Kesehatan\Models\KunjunganUks;
use UnitEnum;

/**
 * WP-27 — Buku kunjungan santri ke UKS (catatan harian).
 *
 * Panel admin untuk petugas UKS: catat keluhan/tindakan/obat, status pulang vs
 * istirahat (rawat di UKS) vs rujuk (ke RS). Status 'istirahat' otomatis menandai
 * PresensiSantri='sakit' lewat KunjunganUksObserver — JANGAN dilewati. Satu santri
 * per tanggal (unik). Sumber data sama dengan PWA Guru UKS (PwaGuruUksController).
 */
class KunjunganUksResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = KunjunganUks::class;

    protected static ?string $slug = 'kesehatan/kunjungan-uks';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Kunjungan UKS';

    protected static ?string $modelLabel = 'Kunjungan UKS';

    protected static ?string $pluralModelLabel = 'Kunjungan UKS';

    protected static UnitEnum|string|null $navigationGroup = 'UKS';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('santri_id')
                ->label('Santri')
                ->relationship('santri', 'nama')
                ->searchable()
                ->preload()
                ->required(),
            DatePicker::make('tanggal')
                ->label('Tanggal')
                ->required()
                ->default(now()),
            TimePicker::make('jam_masuk')
                ->label('Jam Masuk')
                ->seconds(false)
                ->required()
                ->default(now()),
            TimePicker::make('jam_keluar')
                ->label('Jam Keluar')
                ->seconds(false)
                ->helperText('Kosongkan bila santri masih di UKS.'),
            Textarea::make('keluhan')
                ->label('Keluhan')
                ->rows(2),
            Textarea::make('tindakan')
                ->label('Tindakan')
                ->rows(2),
            Textarea::make('obat')
                ->label('Obat Diberikan')
                ->rows(2)
                ->helperText('Tulis nama & jumlah obat yang diberikan (bila ada).'),
            Select::make('status')
                ->label('Status')
                ->options([
                    'pulang' => 'Pulang (langsung kembali)',
                    'istirahat' => 'Istirahat (rawat di UKS → presensi otomatis "sakit")',
                    'rujuk' => 'Rujuk (ke RS/klinik luar)',
                ])
                ->required()
                ->default('pulang')
                ->live(),
            TextInput::make('rujukan_ke')
                ->label('Rujuk ke (RS/Klinik)')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $get('status') === 'rujuk'),
            TextInput::make('rujukan_biaya')
                ->label('Biaya Rujukan')
                ->numeric()
                ->minValue(0)
                ->prefix('Rp')
                ->visible(fn (Get $get): bool => $get('status') === 'rujuk'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->searchable()->sortable(),
                TextColumn::make('tanggal')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('jam_masuk')->label('Masuk')->time('H:i'),
                TextColumn::make('keluhan')->label('Keluhan')->limit(40),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'istirahat' => 'Istirahat',
                        'rujuk' => 'Rujuk',
                        default => 'Pulang',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'rujuk' => 'danger',
                        'istirahat' => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('pencatat.name')->label('Pencatat')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['pulang' => 'Pulang', 'istirahat' => 'Istirahat', 'rujuk' => 'Rujuk']),
            ])
            ->actions([EditAction::make()])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKunjunganUks::route('/'),
            'create' => CreateKunjunganUks::route('/create'),
            'edit' => EditKunjunganUks::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('kesehatan') && (auth()->user()?->can('view_santri') ?? false);
    }
}
