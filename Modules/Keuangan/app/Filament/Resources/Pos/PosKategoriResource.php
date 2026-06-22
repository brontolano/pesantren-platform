<?php

namespace Modules\Keuangan\Filament\Resources\Pos;

use Modules\Keuangan\Filament\Resources\Pos\Pages\CreatePosKategori;
use Modules\Keuangan\Filament\Resources\Pos\Pages\EditPosKategori;
use Modules\Keuangan\Filament\Resources\Pos\Pages\ListPosKategori;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Keuangan\Models\PosKategori;
use Modules\Ledger\Models\Akun;

/**
 * Konfigurasi kategori POS Kas per tenant (Configuration over Customization).
 * Gated: modul keuangan tampak + permission operate_pos.
 */
class PosKategoriResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = PosKategori::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Kategori POS Kas';

    protected static ?string $modelLabel = 'Kategori POS';

    protected static ?string $pluralModelLabel = 'Kategori POS';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 20;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('keuangan') && (auth()->user()?->can('operate_pos') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kategori Transaksi POS')
                ->description('Atur kategori pemasukan/pengeluaran tunai loket. Tiap pondok bebas menyesuaikan.')
                ->schema([
                    TextInput::make('nama')->label('Nama')->required()->maxLength(255),
                    TextInput::make('kode')->label('Kode')->maxLength(40)
                        ->helperText('Unik per pondok (mis. KOPERASI).'),
                    Select::make('jenis')->label('Jenis')->required()
                        ->options(['pemasukan' => 'Pemasukan (kas masuk)', 'pengeluaran' => 'Pengeluaran (kas keluar)']),
                    Select::make('handler')->label('Perilaku')->required()->default('umum')
                        ->options([
                            'umum' => 'Umum (posting langsung)',
                            'koperasi' => 'Koperasi / Kantin',
                            'infaq' => 'Infaq / ZISWAF',
                            'pengeluaran' => 'Pengeluaran kebutuhan',
                            'tabungan_setor' => 'Tabungan — Setor',
                            'tabungan_tarik' => 'Tabungan — Tarik',
                            'daftar_ulang' => 'Daftar Ulang',
                            'spp' => 'SPP / Syahriyah',
                        ])
                        ->helperText('Menentukan integrasi (tabungan/pembayaran) & cara posting Ledger.'),
                    Select::make('akun_id')->label('Akun Lawan (COA)')
                        ->options(fn () => Akun::query()->where('is_postable', true)->orderBy('kode')
                            ->get()->mapWithKeys(fn (Akun $a) => [$a->id => $a->kode.' — '.$a->nama]))
                        ->searchable()
                        ->helperText('Akun pendapatan (pemasukan) / beban (pengeluaran) lawan Kas. Kosong = rekam tunai tanpa jurnal.'),
                    TextInput::make('sort')->label('Urutan')->numeric()->default(0),
                    Toggle::make('aktif')->label('Aktif')->default(true)->inline(false),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('jenis')->label('Jenis')->badge()
                    ->color(fn (?string $state): string => $state === 'pemasukan' ? 'success' : 'danger'),
                TextColumn::make('handler')->label('Perilaku')->badge()->color('gray'),
                TextColumn::make('akun.kode')->label('Akun Lawan')->placeholder('— belum diset'),
                TextColumn::make('aktif')->label('Status')->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn ($state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('sort')
            ->filters([
                SelectFilter::make('jenis')->options(['pemasukan' => 'Pemasukan', 'pengeluaran' => 'Pengeluaran']),
                SelectFilter::make('aktif')->options(['1' => 'Aktif', '0' => 'Nonaktif']),
            ])
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosKategori::route('/'),
            'create' => CreatePosKategori::route('/create'),
            'edit' => EditPosKategori::route('/{record}/edit'),
        ];
    }
}
