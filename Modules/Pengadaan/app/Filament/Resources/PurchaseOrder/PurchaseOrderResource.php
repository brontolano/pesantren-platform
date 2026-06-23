<?php

namespace Modules\Pengadaan\Filament\Resources\PurchaseOrder;

use App\Filament\Concerns\ModuleGate;
use Modules\Pengadaan\Filament\Resources\PurchaseOrder\Pages\CreatePurchaseOrder;
use Modules\Pengadaan\Filament\Resources\PurchaseOrder\Pages\EditPurchaseOrder;
use Modules\Pengadaan\Filament\Resources\PurchaseOrder\Pages\ListPurchaseOrder;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\DivisiUnit;
use Modules\Ledger\Models\SumberDana;
use Modules\Pengadaan\Models\PurchaseOrder;
use Modules\Pengadaan\Models\Supplier;
use Modules\Pengadaan\Services\PurchaseOrderService;

/**
 * G2-09 — Purchase Order. Buat (item) → Setujui (F1 + budget) → Terima (GRN → Belanja).
 */
class PurchaseOrderResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = PurchaseOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Purchase Order';

    protected static ?string $modelLabel = 'Purchase Order';

    protected static ?string $pluralModelLabel = 'Purchase Order';

    protected static string|\UnitEnum|null $navigationGroup = 'Sarana & Logistik';

    protected static ?int $navigationSort = 21;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('pengadaan') && (auth()->user()?->canAny(['view_keuangan', 'input_belanja', 'manage_ledger']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('input_belanja') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof PurchaseOrder && $record->status === 'draft'
            && (auth()->user()?->can('input_belanja') ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Purchase Order')->schema([
                Select::make('supplier_id')->label('Supplier')->required()->searchable()
                    ->options(fn () => Supplier::query()->where('aktif', true)->orderBy('nama')->pluck('nama', 'id')),
                DatePicker::make('tanggal')->label('Tanggal')->required()->default(now()),
                Select::make('jenis')->label('Jenis')->native(false)->default('barang')
                    ->options(['barang' => 'Barang', 'jasa' => 'Jasa', 'sewa' => 'Sewa']),
                Select::make('akun_beban_id')->label('Akun Beban')->searchable()
                    ->options(fn () => Akun::query()->where('tipe', 'BEBAN')->where('is_postable', true)->orderBy('kode')
                        ->get()->mapWithKeys(fn ($a) => [$a->id => $a->kode.' · '.$a->nama])),
                Select::make('divisi_id')->label('Divisi')->searchable()
                    ->options(fn () => DivisiUnit::query()->orderBy('nama_divisi')->pluck('nama_divisi', 'id')),
                Select::make('sumber_dana_id')->label('Sumber Dana')->searchable()
                    ->options(fn () => SumberDana::query()->orderBy('kode_dana')->pluck('nama_sumber', 'id')),
                TextInput::make('keterangan')->label('Keterangan')->maxLength(255)->columnSpanFull(),
            ])->columns(2),
            Section::make('Item')->schema([
                Repeater::make('items')->label('Item PO')
                    ->schema([
                        TextInput::make('uraian')->label('Uraian')->required()->columnSpan(2),
                        TextInput::make('qty')->label('Qty')->numeric()->required()->default(1),
                        TextInput::make('harga_satuan')->label('Harga Satuan')->numeric()->required()->prefix('Rp'),
                    ])->columns(4)->defaultItems(1)->addActionLabel('Tambah Item')
                    ->visibleOn('create'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor')->label('No. PO')->searchable()->sortable(),
                TextColumn::make('supplier.nama')->label('Supplier')->searchable(),
                TextColumn::make('tanggal')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('total')->label('Total')->money('IDR')->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'disetujui' => 'info', 'diterima' => 'success', 'ditolak' => 'danger',
                        'ditutup' => 'gray', default => 'warning',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'draft' => 'Draft', 'diajukan' => 'Diajukan', 'disetujui' => 'Disetujui',
                    'diterima' => 'Diterima', 'ditutup' => 'Ditutup', 'ditolak' => 'Ditolak',
                ]),
            ])
            ->actions([
                Action::make('setujui')->label('Setujui')->icon('heroicon-o-check')->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseOrder $r): bool => in_array($r->status, ['draft', 'diajukan'], true) && (auth()->user()?->can('approve_pencairan') ?? false))
                    ->action(function (PurchaseOrder $r): void {
                        try {
                            app(PurchaseOrderService::class)->setujui($r, auth()->id());
                            Notification::make()->title('PO disetujui')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal menyetujui')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('terima')->label('Terima (GRN)')->icon('heroicon-o-truck')->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Menerima seluruh item PO → membuat Belanja (invoice) untuk diposting ke Buku Besar.')
                    ->visible(fn (PurchaseOrder $r): bool => $r->status === 'disetujui' && (auth()->user()?->can('input_belanja') ?? false))
                    ->action(function (PurchaseOrder $r): void {
                        try {
                            $po = app(PurchaseOrderService::class)->terima($r, [], auth()->id());
                            Notification::make()->title($po->status === 'diterima' ? 'Barang diterima & Belanja dibuat' : 'Penerimaan dicatat')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal menerima')->body($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrder::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
