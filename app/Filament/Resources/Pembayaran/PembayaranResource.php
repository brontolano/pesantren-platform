<?php

namespace App\Filament\Resources\Pembayaran;

use App\Filament\Resources\Pembayaran\Pages\CreatePembayaran;
use App\Filament\Resources\Pembayaran\Pages\ListPembayaran;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\PembayaranService;

class PembayaranResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = Pembayaran::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Pembayaran';

    protected static ?string $modelLabel = 'Pembayaran';

    protected static ?string $pluralModelLabel = 'Pembayaran';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('keuangan') && (auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('keuangan') && (auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_keuangan') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Input Pembayaran')->schema([
                Select::make('tagihan_id')
                    ->label('Tagihan')
                    ->options(fn () => Tagihan::belumLunas()
                        ->with('santri')
                        ->get()
                        ->mapWithKeys(fn ($t) => [
                            $t->id => ($t->santri?->nama ?? '?') . ' - ' . $t->jenis_tagihan . ' (Rp ' . number_format($t->sisa_tagihan) . ')',
                        ]))
                    ->searchable()
                    ->required(),
                TextInput::make('nominal')
                    ->label('Nominal Bayar')
                    ->numeric()
                    ->prefix('Rp')
                    ->required()
                    ->minValue(1),
                Select::make('metode')
                    ->label('Metode')
                    ->options([
                        'tunai' => 'Tunai',
                        'transfer' => 'Transfer Bank',
                        'qris' => 'QRIS',
                    ])
                    ->default('tunai')
                    ->required(),
                DatePicker::make('tanggal_pembayaran')
                    ->label('Tanggal Bayar')
                    ->default(now())
                    ->required(),
                TextInput::make('nomor_bukti')
                    ->label('Nomor Bukti')
                    ->maxLength(100),
                FileUpload::make('bukti_pembayaran')
                    ->label('Bukti Pembayaran')
                    ->image()
                    ->directory('bukti-pembayaran')
                    ->maxSize(2048),
                Textarea::make('catatan')
                    ->label('Catatan')
                    ->rows(2),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')
                    ->label('Santri')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tagihan.jenis_tagihan')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('nominal')
                    ->label('Nominal')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('metode')
                    ->label('Metode')
                    ->badge(),
                TextColumn::make('tanggal_pembayaran')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                IconColumn::make('is_verified')
                    ->label('Verifikasi')
                    ->boolean(),
                TextColumn::make('verifier.name')
                    ->label('Diverifikasi oleh')
                    ->placeholder('-'),
            ])
            ->defaultSort('tanggal_pembayaran', 'desc')
            ->filters([
                TernaryFilter::make('is_verified')
                    ->label('Status Verifikasi'),
                SelectFilter::make('metode')
                    ->options([
                        'tunai' => 'Tunai',
                        'transfer' => 'Transfer Bank',
                        'qris' => 'QRIS',
                    ]),
            ])
            ->actions([
                Action::make('verifikasi')
                    ->label('Verifikasi')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Pembayaran $record) => ! $record->is_verified && auth()->user()?->can('manage_keuangan'))
                    ->action(function (Pembayaran $record): void {
                        (new PembayaranService())->verifikasiPembayaran($record, auth()->id());
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['santri', 'tagihan', 'verifier']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPembayaran::route('/'),
            'create' => CreatePembayaran::route('/create'),
        ];
    }
}

