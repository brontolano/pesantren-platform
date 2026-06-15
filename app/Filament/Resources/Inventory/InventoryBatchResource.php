<?php

namespace App\Filament\Resources\Inventory;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Resources\Inventory\InventoryBatch\Pages\ListInventoryBatch;
use App\Support\UiMode;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Keuangan\Models\InventoryBatch;

/**
 * G2-14 — Pemantauan batch/lot inventory + tanggal kedaluwarsa (read-only).
 */
class InventoryBatchResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = InventoryBatch::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationLabel = 'Batch / Kedaluwarsa';

    protected static ?string $modelLabel = 'Batch';

    protected static ?string $pluralModelLabel = 'Batch / Kedaluwarsa';

    protected static string|\UnitEnum|null $navigationGroup = 'Sarana & Logistik';

    protected static ?int $navigationSort = 23;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('keuangan') && (auth()->user()?->can('operate_pos') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.nama')->label('Item')->searchable(),
                TextColumn::make('nomor_batch')->label('Batch/Lot')->searchable(),
                TextColumn::make('lokasi.nama')->label('Lokasi')->placeholder('—'),
                TextColumn::make('qty')->label('Qty')->alignEnd()
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 3, ',', '.'), '0'), ',')),
                TextColumn::make('tanggal_kedaluwarsa')->label('Kedaluwarsa')->date()->placeholder('—')
                    ->color(fn (?Carbon $state): string => $state !== null && $state->isPast() ? 'danger' : 'success')
                    ->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('tanggal_kedaluwarsa')
            ->filters([
                Filter::make('kedaluwarsa')->label('Sudah kedaluwarsa')
                    ->query(fn (Builder $q): Builder => $q->whereNotNull('tanggal_kedaluwarsa')->whereDate('tanggal_kedaluwarsa', '<', now())),
                Filter::make('tersedia')->label('Masih ada stok')->default()
                    ->query(fn (Builder $q): Builder => $q->where('qty', '>', 0)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryBatch::route('/'),
        ];
    }
}
