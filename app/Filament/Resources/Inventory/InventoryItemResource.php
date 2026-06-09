<?php

namespace App\Filament\Resources\Inventory;

use App\Filament\Resources\Inventory\InventoryItem\Pages\CreateInventoryItem;
use App\Filament\Resources\Inventory\InventoryItem\Pages\EditInventoryItem;
use App\Filament\Resources\Inventory\InventoryItem\Pages\ListInventoryItem;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
use Modules\Keuangan\Services\InventoryService;
use Modules\Ledger\Models\Akun;

/**
 * Inventory Koperasi/Kantin — kelola item + stok (valuasi average cost).
 * Stok & harga pokok dikelola lewat aksi Terima Stok / Opname (jejak mutasi),
 * bukan diedit langsung. Gated modul keuangan + operate_pos.
 */
class InventoryItemResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = InventoryItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Inventory Koperasi';

    protected static ?string $modelLabel = 'Item Inventory';

    protected static ?string $pluralModelLabel = 'Inventory Koperasi';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 21;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('keuangan') && (auth()->user()?->can('operate_pos') ?? false);
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
                EditAction::make(),
                DeleteAction::make(),
            ]);
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
