<?php

namespace App\Filament\Resources\Tenants;

use App\Filament\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Resources\Tenants\Pages\EditTenant;
use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Models\Tenant;
use App\Services\BillingService;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Tenants';

    protected static ?string $modelLabel = 'Tenant';

    protected static ?string $pluralModelLabel = 'Tenants';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::userCanManageTenants();
    }

    public static function canViewAny(): bool
    {
        return static::userCanManageTenants();
    }

    public static function canCreate(): bool
    {
        return static::userCanManageTenants();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::userCanManageTenants();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Tenant')->schema([
                TextInput::make('id')
                    ->label('Tenant ID')
                    ->required()
                    ->alphaDash()
                    ->maxLength(255)
                    ->disabledOn('edit'),
                TextInput::make('name')
                    ->label('Nama Pesantren')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->inline(false),
            ]),
            Section::make('Domain Mapping')->schema([
                TextInput::make('primary_domain')
                    ->label('Subdomain (fallback)')
                    ->helperText('Misal: asy-syifaa → asy-syifaa.pesantren-platform.com')
                    ->required()
                    ->maxLength(255),
                TextInput::make('website_domain')
                    ->label('Domain Website Publik (custom)')
                    ->helperText('Misal: asy-syifaa.sch.id — kosongkan jika pakai subdomain')
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('sistem_domain')
                    ->label('Domain Sistem (panel admin)')
                    ->helperText('Otomatis: sistem.{website_domain}. Isi manual jika berbeda.')
                    ->nullable()
                    ->maxLength(255),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Tenant ID')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama Pesantren')->searchable()->sortable(),
                TextColumn::make('domains.domain')->label('Domain')->badge()->separator(','),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                EditAction::make(),
                Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('danger')
                    ->action(fn (Tenant $record) => $record->update(['is_active' => false]))
                    ->requiresConfirmation()
                    ->visible(fn (Tenant $record): bool => $record->is_active),
                Action::make('activate')
                    ->label('Aktifkan')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->action(fn (Tenant $record) => $record->update(['is_active' => true]))
                    ->visible(fn (Tenant $record): bool => !$record->is_active),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('domains');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'create' => CreateTenant::route('/create'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }

    protected static function userCanManageTenants(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}

