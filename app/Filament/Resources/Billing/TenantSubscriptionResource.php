<?php

namespace App\Filament\Resources\Billing;

use App\Filament\Resources\Billing\TenantSubscription\Pages\CreateTenantSubscription;
use App\Filament\Resources\Billing\TenantSubscription\Pages\EditTenantSubscription;
use App\Filament\Resources\Billing\TenantSubscription\Pages\ListTenantSubscriptions;
use App\Models\TenantSubscription;
use App\Services\BillingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class TenantSubscriptionResource extends Resource
{
    protected static ?string $model = TenantSubscription::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Langganan Tenant';

    protected static UnitEnum|string|null $navigationGroup = 'Langganan & Billing';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('tenant_id')
                ->label('Tenant')
                ->relationship('tenant', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('plan_id')
                ->label('Paket')
                ->relationship('plan', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('billing_cycle')
                ->label('Siklus Billing')
                ->options(['monthly' => 'Bulanan', 'yearly' => 'Tahunan'])
                ->required()
                ->default('monthly'),
            Select::make('status')
                ->label('Status')
                ->options(['active' => 'Aktif', 'suspended' => 'Ditangguhkan', 'cancelled' => 'Dibatalkan'])
                ->required()
                ->default('active'),
            DatePicker::make('starts_at')->label('Mulai')->required()->default(now()),
            DatePicker::make('ends_at')->label('Berakhir')->nullable(),
            KeyValue::make('override')
                ->label('Override (harga/modul khusus)')
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')->label('Tenant')->searchable()->sortable(),
                TextColumn::make('plan.name')->label('Paket')->sortable(),
                TextColumn::make('billing_cycle')->label('Siklus')->badge(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('starts_at')->label('Mulai')->date('d M Y'),
                TextColumn::make('ends_at')->label('Berakhir')->date('d M Y')->placeholder('Tidak terbatas'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['active' => 'Aktif', 'suspended' => 'Ditangguhkan', 'cancelled' => 'Dibatalkan']),
            ])
            ->actions([
                EditAction::make(),
                Action::make('generate_invoice')
                    ->label('Generate Invoice')
                    ->icon('heroicon-o-document-plus')
                    ->color('info')
                    ->action(function (TenantSubscription $record): void {
                        $billing = app(BillingService::class);
                        $period = now()->format('Y-m');
                        $billing->generateInvoice($record->tenant, $period, $record->effectivePrice());
                    })
                    ->requiresConfirmation(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenantSubscriptions::route('/'),
            'create' => CreateTenantSubscription::route('/create'),
            'edit' => EditTenantSubscription::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isPlatformSuperAdmin() ?? false;
    }
}
