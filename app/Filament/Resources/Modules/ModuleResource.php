<?php

namespace App\Filament\Resources\Modules;

use App\Filament\Resources\Modules\Pages\ListModules;
use App\Models\ModuleRegistry;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ModuleResource extends Resource
{
    protected static ?string $model = ModuleRegistry::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Modul Platform';

    protected static ?string $modelLabel = 'Modul';

    protected static ?string $pluralModelLabel = 'Modul Platform';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing SaaS';

    protected static ?int $navigationSort = 5;

    /** Hanya panel platform (central) + super_admin. */
    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin'
            && (auth()->user()?->hasRole('super_admin') ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Modul')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_core')
                    ->label('Inti')
                    ->boolean()
                    ->tooltip('Modul inti selalu aktif untuk semua tenant'),
                SelectColumn::make('status')
                    ->label('Mode')
                    ->options([
                        'released' => 'Released (semua tenant)',
                        'development' => 'Development (demo saja)',
                    ])
                    ->selectablePlaceholder(false)
                    ->disabled(fn (ModuleRegistry $record): bool => $record->is_core)
                    ->rules(['in:released,development']),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'released' ? 'Released' : 'Development')
                    ->color(fn (string $state): string => $state === 'released' ? 'success' : 'warning'),
            ])
            ->defaultSort('sort')
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModules::route('/'),
        ];
    }
}
