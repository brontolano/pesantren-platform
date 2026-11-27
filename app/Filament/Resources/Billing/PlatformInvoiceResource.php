<?php

namespace App\Filament\Resources\Billing;

use App\Filament\Resources\Billing\PlatformInvoice\Pages\CreatePlatformInvoice;
use App\Filament\Resources\Billing\PlatformInvoice\Pages\EditPlatformInvoice;
use App\Filament\Resources\Billing\PlatformInvoice\Pages\ListPlatformInvoices;
use App\Models\PlatformInvoice;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PlatformInvoiceResource extends Resource
{
    protected static ?string $model = PlatformInvoice::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationLabel = 'Tagihan Layanan';

    protected static UnitEnum|string|null $navigationGroup = 'Langganan & Billing';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tagihan layanan')
                ->description('Catatan ini untuk kewajiban layanan atau langganan. Donasi sukarela dicatat terpisah dan tidak memengaruhi status layanan.')
                ->columns(2)
                ->schema([
            Select::make('tenant_id')
                ->label('Tenant')
                ->relationship('tenant', 'name')
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('period')
                ->label('Periode (YYYY-MM)')
                ->required()
                ->placeholder('2026-06'),
            TextInput::make('amount')
                ->label('Jumlah (Rp)')
                ->numeric()
                ->prefix('Rp')
                ->required(),
            Select::make('status')
                ->label('Status')
                ->options(['unpaid' => 'Belum Dibayar', 'paid' => 'Lunas'])
                ->required()
                ->default('unpaid'),
            FileUpload::make('payment_proof')
                ->label('Bukti Pembayaran')
                ->nullable()
                ->directory('billing/proofs'),
            DateTimePicker::make('paid_at')
                ->label('Tanggal Bayar')
                ->nullable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')->label('Tenant')->searchable()->sortable(),
                TextColumn::make('period')->label('Periode')->sortable(),
                TextColumn::make('amount')->label('Jumlah')->money('IDR')->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => $state === 'paid' ? 'success' : 'danger'),
                TextColumn::make('paid_at')->label('Dibayar')->dateTime('d M Y H:i')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['unpaid' => 'Belum Dibayar', 'paid' => 'Lunas']),
            ])
            ->actions([EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatformInvoices::route('/'),
            'create' => CreatePlatformInvoice::route('/create'),
            'edit' => EditPlatformInvoice::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isPlatformSuperAdmin() ?? false;
    }
}
