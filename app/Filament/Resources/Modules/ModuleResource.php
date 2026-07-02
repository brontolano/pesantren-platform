<?php

namespace App\Filament\Resources\Modules;

use App\Filament\Resources\Modules\Pages\CreateModule;
use App\Filament\Resources\Modules\Pages\EditModule;
use App\Filament\Resources\Modules\Pages\ListModules;
use App\Models\ModuleRegistry;
use App\Models\Tenant;
use App\Models\TenantModule;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ModuleResource extends Resource
{
    protected static ?string $model = ModuleRegistry::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Katalog Modul';

    protected static ?string $modelLabel = 'Modul';

    protected static ?string $pluralModelLabel = 'Katalog Modul';

    protected static string|\UnitEnum|null $navigationGroup = 'Langganan & Billing';

    protected static ?int $navigationSort = 5;

    /** Hanya panel platform (central) + super_admin. */
    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin'
            && (auth()->user()?->hasRole('super_admin') ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->label('Key Modul')
                ->helperText('Identifier kode, mis. "akademik". Dipakai di resource: moduleVisible(\'key\').')
                ->required()
                ->alphaDash()
                ->maxLength(50)
                ->disabledOn('edit')
                ->unique(ignoreRecord: true),
            TextInput::make('label')
                ->label('Nama Modul')
                ->required()
                ->maxLength(255),
            Select::make('status')
                ->label('Mode')
                ->options([
                    'released' => 'Released — muncul di semua tenant (sesuai paket)',
                    'development' => 'Development — hanya muncul di demo',
                ])
                ->default('development')
                ->required(),
            Toggle::make('is_core')
                ->label('Modul Inti')
                ->helperText('Modul inti selalu aktif untuk semua tenant & tidak bisa dimatikan.')
                ->default(false),
            TextInput::make('sort')
                ->label('Urutan')
                ->numeric()
                ->default(99),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->label('Modul')->searchable()->sortable(),
                TextColumn::make('key')->label('Key')->badge()->color('gray'),
                IconColumn::make('is_core')->label('Inti')->boolean()
                    ->tooltip('Modul inti selalu aktif untuk semua tenant'),
                SelectColumn::make('status')
                    ->label('Mode (klik untuk ubah)')
                    ->options([
                        'released' => '🟢 Released — semua tenant',
                        'development' => '🟠 Development — demo saja',
                    ])
                    ->selectablePlaceholder(false)
                    ->disabled(fn (ModuleRegistry $record): bool => $record->is_core)
                    ->rules(['in:released,development']),
            ])
            ->defaultSort('sort')
            ->paginated(false)
            ->actions([
                Action::make('release')
                    ->label('Set Release')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('success')
                    ->visible(fn (ModuleRegistry $r): bool => $r->status === 'development')
                    ->requiresConfirmation()
                    ->modalDescription('Tandai modul ini STABIL/RELEASED. Modul akan tersedia untuk semua tenant (aktifkan per tenant lewat Kalkulator Langganan / tombol Sebar).')
                    ->action(fn (ModuleRegistry $r) => $r->update(['status' => 'released'])),
                Action::make('spread')
                    ->label('Sebar ke semua tenant')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('info')
                    ->visible(fn (ModuleRegistry $r): bool => $r->status === 'released' && ! $r->is_core)
                    ->requiresConfirmation()
                    ->modalDescription('Aktifkan modul ini untuk SEMUA tenant aktif.')
                    ->action(function (ModuleRegistry $r) {
                        $tenants = Tenant::all();
                        foreach ($tenants as $t) {
                            TenantModule::updateOrCreate(
                                ['tenant_id' => $t->getKey(), 'module_name' => $r->key],
                                ['is_active' => true]
                            );
                        }
                        Notification::make()
                            ->title('Modul "'.$r->label.'" disebar ke '.$tenants->count().' tenant')
                            ->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (ModuleRegistry $r): bool => ! $r->is_core),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModules::route('/'),
            'create' => CreateModule::route('/create'),
            'edit' => EditModule::route('/{record}/edit'),
        ];
    }
}
