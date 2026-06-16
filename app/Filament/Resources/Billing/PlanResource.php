<?php

namespace App\Filament\Resources\Billing;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\Billing\Plan\Pages\CreatePlan;
use App\Filament\Resources\Billing\Plan\Pages\EditPlan;
use App\Filament\Resources\Billing\Plan\Pages\ListPlans;
use App\Models\Plan;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Paket Langganan';

    protected static UnitEnum|string|null $navigationGroup = 'Langganan & Billing';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->label('Nama Paket')
                ->required()
                ->maxLength(100),
            Select::make('billing_type')
                ->label('Tipe Billing')
                ->options([
                    'per_santri' => 'Per Santri',
                    'flat'       => 'Flat',
                    'custom'     => 'Custom',
                    'gratis'     => 'Gratis',
                ])
                ->required(),
            TextInput::make('default_price')
                ->label('Harga Default (Rp)')
                ->numeric()
                ->prefix('Rp')
                ->default(0),
            CheckboxList::make('default_modules')
                ->label('Modul Default')
                ->options([
                    'Kesantrian' => 'Kesantrian',
                    'Keuangan'   => 'Keuangan',
                    'PPDB'       => 'PPDB',
                    'CMS'        => 'CMS/Website',
                    'Akademik'   => 'Akademik',
                    'Asrama'     => 'Asrama',
                    'Tahfidz'    => 'Tahfidz',
                    'Kesehatan'  => 'Kesehatan',
                    'Kegiatan'   => 'Kegiatan',
                    'Notifikasi' => 'Notifikasi',
                ])
                ->columns(2),
            Textarea::make('description')
                ->label('Deskripsi')
                ->rows(3),
            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Paket')->searchable()->sortable(),
                TextColumn::make('billing_type')->label('Tipe')->badge(),
                TextColumn::make('default_price')
                    ->label('Harga')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('subscriptions_count')
                    ->label('Tenant')
                    ->counts('subscriptions')
                    ->badge(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit'   => EditPlan::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}
