<?php

namespace Modules\Asrama\Filament\Resources\BiayaAsrama;

use Modules\Asrama\Filament\Resources\BiayaAsrama\Pages\CreateBiayaAsrama;
use Modules\Asrama\Filament\Resources\BiayaAsrama\Pages\EditBiayaAsrama;
use Modules\Asrama\Filament\Resources\BiayaAsrama\Pages\ListBiayaAsrama;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Asrama\Models\BiayaAsrama;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Kesantrian\Models\Santri;

class BiayaAsramaResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;
    protected static ?string $model = BiayaAsrama::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Biaya Asrama';

    protected static string|\UnitEnum|null $navigationGroup = 'Santri & Pengasuhan';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('asrama') && (auth()->user()?->canAny(['view_keuangan', 'manage_keuangan', 'manage_santri']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('asrama') && (auth()->user()?->canAny(['view_keuangan', 'manage_keuangan', 'manage_santri']) ?? false);
    }

    public static function canCreate(): bool
    {
        return static::moduleVisible('asrama') && (auth()->user()?->canAny(['manage_keuangan', 'manage_santri']) ?? false);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::moduleVisible('asrama') && (auth()->user()?->canAny(['manage_keuangan', 'manage_santri']) ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('santri_id')
                ->label('Santri')
                ->options(fn () => Santri::query()->orderBy('nama')->pluck('nama', 'id'))
                ->required()
                ->searchable(),
            Select::make('kamar_id')
                ->label('Kamar')
                ->options(fn () => KamarAsrama::query()
                    ->with('blok')
                    ->get()
                    ->mapWithKeys(fn (KamarAsrama $kamar) => [$kamar->id => $kamar->blok->nama_blok . ' - ' . $kamar->nomor_kamar]))
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, $set): void {
                    // Prefill nominal dari biaya/bulan kamar agar staf tak salah ketik.
                    $biaya = $state ? KamarAsrama::find($state)?->biaya_per_bulan : null;
                    if ($biaya !== null) {
                        $set('nominal', (string) $biaya);
                    }
                })
                ->nullable(),
            Select::make('periode')
                ->label('Periode (bulan)')
                ->options(fn (?BiayaAsrama $record): array => static::periodeOptions($record?->periode))
                ->default(now()->format('Y-m'))
                ->required()
                ->searchable()
                ->helperText('Pilih bulan tagihan — format seragam YYYY-MM agar laporan rapi.'),
            TextInput::make('nominal')->label('Nominal (Rp)')->numeric()->required()->minValue(0)
                ->helperText('Terisi otomatis dari biaya/bulan kamar saat kamar dipilih; boleh disesuaikan.'),
            Select::make('status_pembayaran')
                ->options([
                    'belum_bayar' => 'Belum Bayar',
                    'sebagian' => 'Sebagian',
                    'lunas' => 'Lunas',
                ])
                ->default('belum_bayar')
                ->required(),
            DatePicker::make('jatuh_tempo'),
        ])->columns(2);
    }

    /**
     * Opsi periode bulanan SERAGAM (YYYY-MM) — jendela -6..+11 bulan dari sekarang.
     * Nilai lama (mis. teks bebas legacy "Juli 2026") dipertahankan agar tak hilang saat edit.
     *
     * @return array<string, string>
     */
    protected static function periodeOptions(?string $current = null): array
    {
        $bulan = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
            '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
        ];

        $opts = [];
        $mulai = now()->startOfMonth()->subMonths(6);
        for ($i = 0; $i < 18; $i++) {
            $d = $mulai->copy()->addMonths($i);
            $opts[$d->format('Y-m')] = $bulan[$d->format('m')].' '.$d->format('Y');
        }

        if ($current !== null && $current !== '' && ! isset($opts[$current])) {
            $opts[$current] = $current.' (lama)';
            ksort($opts);
        }

        return $opts;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->searchable()->sortable(),
                TextColumn::make('periode')->sortable(),
                TextColumn::make('nominal')->money('IDR', locale: 'id'),
                TextColumn::make('status_pembayaran')->badge(),
                TextColumn::make('tagihanKeuangan.id')->label('Tagihan')->placeholder('-'),
            ])
            ->defaultSort('periode', 'desc')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['santri', 'tagihanKeuangan']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBiayaAsrama::route('/'),
            'create' => CreateBiayaAsrama::route('/create'),
            'edit' => EditBiayaAsrama::route('/{record}/edit'),
        ];
    }
}

