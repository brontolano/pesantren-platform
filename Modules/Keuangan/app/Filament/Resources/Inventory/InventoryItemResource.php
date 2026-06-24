<?php

namespace Modules\Keuangan\Filament\Resources\Inventory;

use App\Filament\Concerns\ModuleGate;
use Modules\Keuangan\Filament\Resources\Inventory\InventoryItem\Pages\CreateInventoryItem;
use Modules\Keuangan\Filament\Resources\Inventory\InventoryItem\Pages\EditInventoryItem;
use Modules\Keuangan\Filament\Resources\Inventory\InventoryItem\Pages\ListInventoryItem;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Keuangan\Models\InventoryItem;
use Modules\Keuangan\Models\InventoryLokasi;
use Modules\Keuangan\Services\InventoryLokasiService;
use Modules\Keuangan\Services\InventoryService;
use Modules\Ledger\Models\Akun;

/**
 * Inventory Koperasi/Kantin — kelola item + stok (valuasi average cost).
 * Stok & harga pokok dikelola lewat aksi Terima Stok / Opname (jejak mutasi),
 * bukan diedit langsung. Gated modul keuangan + operate_pos.
 */
class InventoryItemResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = InventoryItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Inventory Koperasi';

    protected static ?string $modelLabel = 'Item Inventory';

    protected static ?string $pluralModelLabel = 'Inventory Koperasi';

    protected static string|\UnitEnum|null $navigationGroup = 'Stok Barang';

    protected static ?int $navigationSort = 21;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('koperasi') && (auth()->user()?->can('operate_pos') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Item')
                ->description('Stok & harga pokok dikelola lewat aksi Terima Stok / Opname pada daftar.')
                ->schema([
                    TextInput::make('nama')->label('Nama')->required()->maxLength(255),
                    TextInput::make('kode')->label('Kode / SKU')->required()->maxLength(50)
                        ->helperText('Unik per pondok.'),
                    TextInput::make('satuan')->label('Satuan')->default('pcs')->maxLength(30),
                    TextInput::make('harga_jual')->label('Harga Jual')->numeric()->default(0)->minValue(0)->prefix('Rp'),
                    Select::make('akun_persediaan_id')->label('Akun Persediaan (COA)')
                        ->options(fn (): array => Akun::query()->where('is_postable', true)->orderBy('kode')
                            ->get()->mapWithKeys(fn (Akun $a): array => [$a->id => $a->kode.' — '.$a->nama])->all())
                        ->searchable()->placeholder('— opsional'),
                    Select::make('akun_hpp_id')->label('Akun HPP (COA)')
                        ->options(fn (): array => Akun::query()->where('is_postable', true)->orderBy('kode')
                            ->get()->mapWithKeys(fn (Akun $a): array => [$a->id => $a->kode.' — '.$a->nama])->all())
                        ->searchable()->placeholder('— opsional'),
                    Toggle::make('aktif')->label('Aktif')->default(true)->inline(false),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode')->searchable()->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('stok')->label('Stok')
                    ->formatStateUsing(fn ($state, InventoryItem $r): string => rtrim(rtrim(number_format((float) $state, 3, ',', '.'), '0'), ',').' '.$r->satuan)
                    ->color(fn ($state): string => (float) $state <= 0 ? 'danger' : 'success')->alignEnd(),
                TextColumn::make('harga_pokok')->label('HPP/unit')
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((float) $state, 0, ',', '.'))->alignEnd(),
                TextColumn::make('harga_jual')->label('Harga Jual')
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((float) $state, 0, ',', '.'))->alignEnd(),
                TextColumn::make('nilai_persediaan')->label('Nilai Stok')
                    ->state(fn (InventoryItem $r): float => $r->nilai_persediaan)
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((float) $state, 0, ',', '.'))->alignEnd(),
                TextColumn::make('aktif')->label('Status')->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn ($state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('nama')
            ->filters([
                SelectFilter::make('aktif')->options(['1' => 'Aktif', '0' => 'Nonaktif']),
            ])
            ->actions([
                Action::make('terima')
                    ->label('Terima Stok')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    // Sembunyikan jalur item-level begitu item dikelola per-lokasi,
                    // agar item.stok tak divergen dari Σ stok per lokasi.
                    ->visible(fn (InventoryItem $record): bool => $record->stokLokasi()->exists() === false)
                    ->schema([
                        TextInput::make('qty')->label('Jumlah masuk')->numeric()->required()->minValue(0.001),
                        TextInput::make('harga_beli')->label('Harga beli / unit')->numeric()->required()->minValue(0)->prefix('Rp'),
                        TextInput::make('keterangan')->label('Keterangan')->maxLength(255),
                    ])
                    ->action(function (array $data, InventoryItem $record): void {
                        app(InventoryService::class)->terima($record, (float) $data['qty'], (float) $data['harga_beli'], [
                            'keterangan' => $data['keterangan'] ?? 'Penerimaan stok',
                            'dibuat_oleh' => auth()->id(),
                        ]);
                        Notification::make()->title('Stok diterima — HPP rata-rata diperbarui')->success()->send();
                    }),
                Action::make('opname')
                    ->label('Opname')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('warning')
                    ->visible(fn (InventoryItem $record): bool => $record->stokLokasi()->exists() === false)
                    ->schema([
                        TextInput::make('stok_fisik')->label('Stok fisik hasil hitung')->numeric()->required()->minValue(0),
                        TextInput::make('keterangan')->label('Keterangan')->maxLength(255),
                    ])
                    ->action(function (array $data, InventoryItem $record): void {
                        app(InventoryService::class)->penyesuaian($record, (float) $data['stok_fisik'], [
                            'keterangan' => $data['keterangan'] ?? 'Penyesuaian opname',
                            'dibuat_oleh' => auth()->id(),
                        ]);
                        Notification::make()->title('Stok disesuaikan ke hasil opname')->success()->send();
                    }),
                Action::make('terima_lokasi')
                    ->label('Terima ke Lokasi')
                    ->icon('heroicon-o-building-storefront')
                    ->color('success')
                    ->visible(fn (): bool => InventoryLokasi::query()->where('is_active', true)->exists())
                    ->schema([
                        Select::make('lokasi_id')->label('Lokasi')->required()->native(false)
                            ->options(fn (): array => static::lokasiOptions()),
                        TextInput::make('qty')->label('Jumlah masuk')->numeric()->required()->minValue(0.001),
                        TextInput::make('harga_beli')->label('Harga beli / unit')->numeric()->required()->minValue(0)->prefix('Rp'),
                        TextInput::make('nomor_batch')->label('Nomor Batch/Lot')->maxLength(80)->placeholder('opsional'),
                        DatePicker::make('tanggal_kedaluwarsa')->label('Tanggal Kedaluwarsa')->placeholder('opsional'),
                    ])
                    ->action(function (array $data, InventoryItem $record): void {
                        app(InventoryLokasiService::class)->terimaDiLokasi($record, (int) $data['lokasi_id'], (float) $data['qty'], (float) $data['harga_beli'], [
                            'nomor_batch' => $data['nomor_batch'] ?: null,
                            'tanggal_kedaluwarsa' => $data['tanggal_kedaluwarsa'] ?? null,
                            'dibuat_oleh' => auth()->id(),
                        ]);
                        Notification::make()->title('Stok diterima di lokasi')->success()->send();
                    }),
                Action::make('transfer')
                    ->label('Transfer Lokasi')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('info')
                    ->visible(fn (): bool => InventoryLokasi::query()->where('is_active', true)->count() >= 2)
                    ->schema([
                        Select::make('dari')->label('Dari Lokasi')->required()->native(false)->options(fn (): array => static::lokasiOptions()),
                        Select::make('ke')->label('Ke Lokasi')->required()->native(false)->options(fn (): array => static::lokasiOptions()),
                        TextInput::make('qty')->label('Jumlah transfer')->numeric()->required()->minValue(0.001),
                    ])
                    ->action(function (array $data, InventoryItem $record): void {
                        try {
                            app(InventoryLokasiService::class)->transfer($record, (int) $data['dari'], (int) $data['ke'], (float) $data['qty'], [
                                'dibuat_oleh' => auth()->id(),
                            ]);
                            Notification::make()->title('Transfer antar lokasi berhasil')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Transfer gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('opname_lokasi')
                    ->label('Opname Lokasi')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('warning')
                    ->visible(fn (): bool => InventoryLokasi::query()->where('is_active', true)->exists()
                        && (auth()->user()?->can('manage_keuangan') ?? false))
                    ->schema([
                        Select::make('lokasi_id')->label('Lokasi')->required()->native(false)->options(fn (): array => static::lokasiOptions()),
                        TextInput::make('stok_fisik')->label('Stok fisik di lokasi')->numeric()->required()->minValue(0),
                        TextInput::make('keterangan')->label('Keterangan')->maxLength(255),
                    ])
                    ->action(function (array $data, InventoryItem $record): void {
                        try {
                            app(InventoryLokasiService::class)->opnameLokasi($record, (int) $data['lokasi_id'], (float) $data['stok_fisik'], [
                                'keterangan' => $data['keterangan'] ?? 'Opname lokasi',
                                'dibuat_oleh' => auth()->id(),
                            ]);
                            Notification::make()->title('Opname lokasi — selisih nilai diposting ke Buku Besar')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Opname gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /** @return array<int, string> id => "KODE — Nama" lokasi aktif. */
    protected static function lokasiOptions(): array
    {
        return InventoryLokasi::query()->where('is_active', true)->orderBy('nama')
            ->get()->mapWithKeys(fn (InventoryLokasi $l): array => [$l->id => $l->kode.' — '.$l->nama])->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryItem::route('/'),
            'create' => CreateInventoryItem::route('/create'),
            'edit' => EditInventoryItem::route('/{record}/edit'),
        ];
    }
}
